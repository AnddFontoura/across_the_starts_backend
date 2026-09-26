<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Energy snapshot for a combatant fleet.
     *
     * - energy: the energy the fleet entered the battle with (drains as it
     *   attacks/defends). A fleet at 0 energy skips its move + attack.
     * - energy_upkeep: energy spent PER SURVIVING SHIP per combat action, so
     *   the round cost falls as ships are lost. 0 = never runs out of energy.
     *
     * Enemy fleets typically leave these at 0 (no upkeep, treated as
     * unlimited) unless the investigation configures them.
     */
    public function up(): void
    {
        Schema::table('battle_fleets', function (Blueprint $table) {
            $table->unsignedBigInteger('energy')->default(0)->after('defense_percent');
            $table->unsignedBigInteger('energy_upkeep')->default(0)->after('energy');
        });
    }

    public function down(): void
    {
        Schema::table('battle_fleets', function (Blueprint $table) {
            $table->dropColumn(['energy', 'energy_upkeep']);
        });
    }
};
