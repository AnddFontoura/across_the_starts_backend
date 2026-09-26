<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One stack of ships in an enemy fleet. Enemy compositions are built from
     * aircraft_types + a module loadout (a snapshot, so they don't depend on
     * any player's ship_designs). Per-ship stats are computed from the base
     * hull + the modules JSON at battle-build time, mirroring how a player
     * ShipDesign is summarized.
     *
     * `modules` is a JSON array of {module_type_id, quantity} entries.
     */
    public function up(): void
    {
        Schema::create('investigation_enemy_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investigation_enemy_fleet_id')
                ->constrained('investigation_enemy_fleets')
                ->cascadeOnDelete();

            $table->foreignId('aircraft_type_id')
                ->constrained('aircraft_types')
                ->cascadeOnDelete();

            // Label shown in the battle log / viewer for this ship stack.
            $table->string('name')->nullable();

            // Module loadout snapshot: [{module_type_id, quantity}, ...].
            $table->json('modules')->nullable();

            $table->unsignedInteger('quantity')->default(1);

            $table->timestamps();

            $table->index('investigation_enemy_fleet_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investigation_enemy_slots');
    }
};
