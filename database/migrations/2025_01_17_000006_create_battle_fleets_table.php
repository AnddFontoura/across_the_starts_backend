<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A combatant fleet inside a battle instance. Player fleets reference the
     * source Fleet (locked out of the base for the run's duration); enemy
     * fleets reference the investigation_enemy_fleet they were built from.
     *
     * Combat-relevant snapshot: commander velocidade (initiative), flat
     * attack/defense percentages, and the fleet's current map position.
     */
    public function up(): void
    {
        Schema::create('battle_fleets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('battle_instance_id')
                ->constrained('battle_instances')
                ->cascadeOnDelete();

            // player | enemy
            $table->string('side');

            // Source references (nullable depending on side).
            $table->foreignId('fleet_id')->nullable()
                ->constrained('fleets')->nullOnDelete();
            $table->foreignId('commander_id')->nullable()
                ->constrained('commanders')->nullOnDelete();
            $table->foreignId('investigation_enemy_fleet_id')->nullable()
                ->constrained('investigation_enemy_fleets')->nullOnDelete();

            $table->string('name');

            // Initiative + flat combat modifiers.
            $table->integer('velocidade')->default(0);
            $table->integer('attack_percent')->default(0);
            $table->integer('defense_percent')->default(0);

            // Current position on the isolated map.
            $table->integer('x')->default(0);
            $table->integer('y')->default(0);

            // Derived once at build time: fleet movement budget (steps of 10).
            $table->unsignedSmallInteger('movement')->default(1);

            // Set to false when all its ships are destroyed.
            $table->boolean('alive')->default(true);

            $table->timestamps();

            $table->index(['battle_instance_id', 'side']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('battle_fleets');
    }
};
