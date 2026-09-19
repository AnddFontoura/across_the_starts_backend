<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add level/upgrade configuration to the structure catalog.
     *
     * Values for a given level are derived from a base value and a per-level
     * growth multiplier:  value(level) = round(base * growth^(level - 1)).
     * Individual levels can still be overridden via structure_level_configs.
     */
    public function up(): void
    {
        Schema::table('structure_types', function (Blueprint $table) {
            // What the structure does: produces a resource or stores/protects it.
            $table->string('category')->default('producer')->after('resource'); // producer | storage

            $table->unsignedInteger('max_level')->default(30)->after('production_per_hour');

            // Production per hour scaling (producers only).
            $table->unsignedInteger('production_base')->default(0)->after('max_level');
            $table->decimal('production_growth', 6, 3)->default(1.150)->after('production_base');

            // Max storable capacity scaling (producers: production ceiling).
            $table->unsignedBigInteger('capacity_base')->default(0)->after('production_growth');
            $table->decimal('capacity_growth', 6, 3)->default(1.200)->after('capacity_base');

            // Protection scaling (storage: amount of EACH resource kept safe).
            $table->unsignedBigInteger('protection_base')->default(0)->after('capacity_growth');
            $table->decimal('protection_growth', 6, 3)->default(1.250)->after('protection_base');

            // Upgrade cost scaling, split across the three resources so an
            // upgrade can cost one, some, or all of them.
            $table->unsignedBigInteger('upgrade_cost_gold_base')->default(0)->after('protection_growth');
            $table->decimal('upgrade_cost_gold_growth', 6, 3)->default(1.500)->after('upgrade_cost_gold_base');
            $table->unsignedBigInteger('upgrade_cost_metal_base')->default(0)->after('upgrade_cost_gold_growth');
            $table->decimal('upgrade_cost_metal_growth', 6, 3)->default(1.500)->after('upgrade_cost_metal_base');
            $table->unsignedBigInteger('upgrade_cost_energy_base')->default(0)->after('upgrade_cost_metal_growth');
            $table->decimal('upgrade_cost_energy_growth', 6, 3)->default(1.500)->after('upgrade_cost_energy_base');

            // Time (in seconds) to build the structure and to upgrade it.
            $table->unsignedInteger('build_time')->default(0)->after('upgrade_cost_energy_growth'); // seconds
            $table->unsignedInteger('upgrade_time_base')->default(0)->after('build_time');          // seconds (level 2)
            $table->decimal('upgrade_time_growth', 6, 3)->default(1.400)->after('upgrade_time_base');
        });
    }

    public function down(): void
    {
        Schema::table('structure_types', function (Blueprint $table) {
            $table->dropColumn([
                'category',
                'max_level',
                'production_base',
                'production_growth',
                'capacity_base',
                'capacity_growth',
                'protection_base',
                'protection_growth',
                'upgrade_cost_gold_base',
                'upgrade_cost_gold_growth',
                'upgrade_cost_metal_base',
                'upgrade_cost_metal_growth',
                'upgrade_cost_energy_base',
                'upgrade_cost_energy_growth',
                'build_time',
                'upgrade_time_base',
                'upgrade_time_growth',
            ]);
        });
    }
};
