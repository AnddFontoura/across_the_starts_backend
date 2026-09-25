<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Support structures (e.g. the Aircraft Hangar) reduce the build time of
     * aircraft by a percentage that scales with level. Stored as a base+growth
     * pair like every other level-scaled stat; the value is a percentage.
     */
    public function up(): void
    {
        Schema::table('structure_types', function (Blueprint $table) {
            // Percentage reduction to aircraft build time (0..100), per level.
            $table->unsignedInteger('build_time_reduction_base')->default(0)->after('range_growth');
            $table->decimal('build_time_reduction_growth', 6, 3)->default(0)->after('build_time_reduction_base');
        });
    }

    public function down(): void
    {
        Schema::table('structure_types', function (Blueprint $table) {
            $table->dropColumn(['build_time_reduction_base', 'build_time_reduction_growth']);
        });
    }
};
