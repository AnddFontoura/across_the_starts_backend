<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aircraft build queue. Each row is one aircraft being built. Orders are
     * distributed across the hangar's build slots; within a slot they build
     * sequentially, so `finishes_at` of an order chains off the previous one
     * in the same slot. When `finishes_at` passes, the order is settled into
     * the player's fleet (aircraft.quantity) and removed.
     */
    public function up(): void
    {
        Schema::create('aircraft_build_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('aircraft_type_id')->constrained('aircraft_types')->cascadeOnDelete();
            $table->unsignedInteger('slot'); // which parallel queue (0-based)
            $table->timestamp('finishes_at'); // when this unit completes
            $table->timestamps();

            $table->index(['user_id', 'slot', 'finishes_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aircraft_build_orders');
    }
};
