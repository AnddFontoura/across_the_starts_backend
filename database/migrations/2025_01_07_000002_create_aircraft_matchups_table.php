<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Counter matrix between aircraft types. One row per ordered pair
     * (attacker -> defender):
     *  - damage_bonus_percent: extra % damage the attacker deals to the defender.
     *  - damage_reduction_percent: % damage the defender mitigates from the
     *    attacker.
     * Both are admin-configurable and consumed later by combat resolution.
     */
    public function up(): void
    {
        Schema::create('aircraft_matchups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attacker_type_id')->constrained('aircraft_types')->cascadeOnDelete();
            $table->foreignId('defender_type_id')->constrained('aircraft_types')->cascadeOnDelete();
            $table->integer('damage_bonus_percent')->default(0);
            $table->integer('damage_reduction_percent')->default(0);
            $table->timestamps();

            $table->unique(['attacker_type_id', 'defender_type_id'], 'aircraft_matchup_pair_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aircraft_matchups');
    }
};
