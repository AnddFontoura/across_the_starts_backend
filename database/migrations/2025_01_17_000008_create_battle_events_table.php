<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ordered log of everything that happened in a battle, one row per atomic
     * action, grouped by round. The frontend plays these back to animate the
     * fight round-by-round; a returning player replays the exact same log.
     *
     * `type`: move | attack | destroy | round_start | round_end | battle_end
     * `payload`: type-specific JSON (from/to positions, damage, kills, etc.).
     */
    public function up(): void
    {
        Schema::create('battle_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('battle_instance_id')
                ->constrained('battle_instances')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('round')->default(0);

            // Monotonic ordering within the whole battle.
            $table->unsignedInteger('sequence')->default(0);

            $table->string('type');

            // Actor / target battle fleet ids (nullable for round markers).
            $table->foreignId('actor_fleet_id')->nullable()
                ->constrained('battle_fleets')->nullOnDelete();
            $table->foreignId('target_fleet_id')->nullable()
                ->constrained('battle_fleets')->nullOnDelete();

            $table->json('payload')->nullable();

            $table->timestamps();

            $table->index(['battle_instance_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('battle_events');
    }
};
