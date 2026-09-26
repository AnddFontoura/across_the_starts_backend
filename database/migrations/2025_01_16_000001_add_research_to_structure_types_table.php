<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Research structures (the "Centro de Pesquisa") unlock the research panel
     * and speed up research the higher their level. Research time is reduced by
     * a percentage that scales with level, capped at a hard ceiling (60%):
     *
     *     reduction(level) = min(cap, base + per_level * (level - 1))
     *
     * e.g. base 0, +3/level, cap 60: level 1 = 0%, level 21 = 60% (then flat).
     *
     * The reduction only ever affects the WAIT TIME of a research, never its
     * gold cost or the required inventory items.
     */
    public function up(): void
    {
        Schema::table('structure_types', function (Blueprint $table) {
            // Percent of research time removed at level 1.
            $table->unsignedInteger('research_time_reduction_base')->default(0)->after('inventory_slots_per_level');
            // Extra percent removed per level beyond the first.
            $table->unsignedInteger('research_time_reduction_per_level')->default(0)->after('research_time_reduction_base');
            // Hard ceiling for the reduction (defaults to 60%).
            $table->unsignedInteger('research_time_reduction_cap')->default(60)->after('research_time_reduction_per_level');
        });
    }

    public function down(): void
    {
        Schema::table('structure_types', function (Blueprint $table) {
            $table->dropColumn([
                'research_time_reduction_base',
                'research_time_reduction_per_level',
                'research_time_reduction_cap',
            ]);
        });
    }
};
