<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Technologies are organised by AREA so the research panel and the admin
     * can group them:
     *  - terrestrial: economy of the terrestrial base (production, storage...)
     *  - aerial: aircraft / fleet bonuses
     *  - weapons: weapon damage or damage-type effects
     *
     * Plants don't use an area (nullable). Every research may also carry a JSON
     * list of gameplay EFFECTS applied per completed level (see
     * ResearchBonusService), e.g.:
     *   [{"type":"resource_production","resource":"gold","percent_per_level":5},
     *    {"type":"warehouse_protection","flat_per_level":1000000},
     *    {"type":"weapon_damage","weapon":"laser","percent_per_level":3},
     *    {"type":"aircraft_class_attack","class":"cruiser","percent_per_level":4}]
     */
    public function up(): void
    {
        Schema::table('research_definitions', function (Blueprint $table) {
            // 'terrestrial' | 'aerial' | 'weapons' (nullable for plants).
            $table->string('area')->nullable()->after('type');
            // JSON list of gameplay effects applied per completed level.
            $table->json('effects')->nullable()->after('area');

            $table->index('area');
        });
    }

    public function down(): void
    {
        Schema::table('research_definitions', function (Blueprint $table) {
            $table->dropIndex(['area']);
            $table->dropColumn(['area', 'effects']);
        });
    }
};
