<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Support structures (the Aircraft Hangar) also govern the player's fleet:
     *  - build_slots: how many aircraft build queues run in parallel.
     *  - fleet_capacity: how many aircraft the account can hold (ready + in
     *    progress). Both scale with the hangar's level (base + growth).
     */
    public function up(): void
    {
        Schema::table('structure_types', function (Blueprint $table) {
            $table->unsignedInteger('build_slots_base')->default(0)->after('build_time_reduction_growth');
            $table->decimal('build_slots_growth', 6, 3)->default(0)->after('build_slots_base');
            $table->unsignedInteger('fleet_capacity_base')->default(0)->after('build_slots_growth');
            $table->decimal('fleet_capacity_growth', 6, 3)->default(0)->after('fleet_capacity_base');
        });
    }

    public function down(): void
    {
        Schema::table('structure_types', function (Blueprint $table) {
            $table->dropColumn([
                'build_slots_base',
                'build_slots_growth',
                'fleet_capacity_base',
                'fleet_capacity_growth',
            ]);
        });
    }
};
