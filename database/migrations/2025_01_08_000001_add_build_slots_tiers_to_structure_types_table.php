<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional level-tier table for aircraft build slots, stored as JSON so an
     * admin can tune the breakpoints without a schema change. Shape:
     *   [{"upTo": 8, "slots": 1}, {"upTo": 16, "slots": 2}, ...]
     * When present it takes precedence over the build_slots_base/growth
     * formula; when null, the formula is used.
     */
    public function up(): void
    {
        Schema::table('structure_types', function (Blueprint $table) {
            $table->json('build_slots_tiers')->nullable()->after('build_slots_growth');
        });
    }

    public function down(): void
    {
        Schema::table('structure_types', function (Blueprint $table) {
            $table->dropColumn('build_slots_tiers');
        });
    }
};
