<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One stack of ships within a fleet: a ship design and a quantity (<=5000).
     * A fleet has at most 12 slots (enforced in the service). The same design
     * can appear only once per fleet.
     */
    public function up(): void
    {
        Schema::create('fleet_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fleet_id')->constrained('fleets')->cascadeOnDelete();
            $table->foreignId('ship_design_id')->constrained('ship_designs')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();

            $table->unique(['fleet_id', 'ship_design_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_slots');
    }
};
