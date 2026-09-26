<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A live run of an investigation for one player. Server-authoritative: the
     * battle state (fleets, ships, positions, hp) lives in child tables and
     * advances one round at a time as the player watches. A stored `seed` keeps
     * resolution deterministic so a returning player replays the same fight.
     */
    public function up(): void
    {
        Schema::create('battle_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('investigation_definition_id')
                ->constrained('investigation_definitions')
                ->cascadeOnDelete();

            // in_progress | won | lost | expired | abandoned
            $table->string('status')->default('in_progress');

            $table->unsignedSmallInteger('current_round')->default(0);
            $table->unsignedSmallInteger('max_rounds')->default(30);

            $table->unsignedInteger('map_width')->default(200);
            $table->unsignedInteger('map_height')->default(200);

            // Deterministic RNG seed for the whole run.
            $table->unsignedBigInteger('seed');

            // Whether end-of-battle settlement (permanent losses applied to the
            // player's Aircraft inventory, prizes/exp granted) has run.
            $table->boolean('settled')->default(false);

            // Whether the player has claimed the win rewards (prizes + exp).
            $table->boolean('rewards_claimed')->default(false);

            $table->timestamp('finished_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('battle_instances');
    }
};
