<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A fleet led by a commander. Composed of up to 12 ship-design slots, each
     * stacking up to 5000 ships of that design (see fleet_slots). Ships come
     * from the player's built fleet inventory and are reserved while assigned.
     */
    public function up(): void
    {
        Schema::create('fleets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commander_id')->nullable()->constrained('commanders')->nullOnDelete();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleets');
    }
};
