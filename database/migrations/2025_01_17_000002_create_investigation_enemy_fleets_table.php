<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A predefined enemy fleet within an investigation definition. Enemy fleets
     * are self-contained (not tied to any player): the admin sets a name, a
     * fixed start position on the isolated map, and NPC "commander" stats that
     * drive initiative (velocidade) and provide flat combat percentages. Its
     * ship composition lives in investigation_enemy_slots.
     */
    public function up(): void
    {
        Schema::create('investigation_enemy_fleets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investigation_definition_id')
                ->constrained('investigation_definitions')
                ->cascadeOnDelete();

            $table->string('name');

            // Fixed spawn position on the isolated map (top-left of the marker).
            $table->unsignedInteger('start_x')->default(0);
            $table->unsignedInteger('start_y')->default(0);

            // NPC commander proxy. velocidade drives initiative order; the two
            // percentages are flat combat modifiers (stand in for a commander's
            // proficiency/attribute bonuses on the enemy side).
            $table->integer('commander_velocidade')->default(0);
            $table->integer('attack_percent')->default(0);
            $table->integer('defense_percent')->default(0);

            $table->timestamps();

            $table->index('investigation_definition_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investigation_enemy_fleets');
    }
};
