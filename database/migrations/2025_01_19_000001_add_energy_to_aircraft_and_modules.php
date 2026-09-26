<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Energy system attributes for ships and modules.
     *
     * - energy_capacity: how much energy this ship/module contributes to a
     *   fleet's energy tank. A design's total tank = base + sum(modules).
     * - energy_upkeep: how much energy ONE ship spends per combat action
     *   (attacking or defending). The fleet's per-round drain scales with the
     *   number of surviving ships.
     *
     * Both default to 0 so existing content is unaffected until configured.
     */
    public function up(): void
    {
        Schema::table('aircraft_types', function (Blueprint $table) {
            $table->unsignedBigInteger('energy_capacity')->default(0)->after('movement');
            $table->unsignedBigInteger('energy_upkeep')->default(0)->after('energy_capacity');
        });
        Schema::table('module_types', function (Blueprint $table) {
            $table->unsignedBigInteger('energy_capacity')->default(0)->after('range');
            $table->unsignedBigInteger('energy_upkeep')->default(0)->after('energy_capacity');
        });
    }

    public function down(): void
    {
        Schema::table('aircraft_types', function (Blueprint $table) {
            $table->dropColumn(['energy_capacity', 'energy_upkeep']);
        });
        Schema::table('module_types', function (Blueprint $table) {
            $table->dropColumn(['energy_capacity', 'energy_upkeep']);
        });
    }
};
