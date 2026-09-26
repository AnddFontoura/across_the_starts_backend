<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin-configurable "Investigação Interplanetária" templates. Each one is
     * a predefined battle the player can launch from the operations center
     * (command_center). It defines how many player fleets may be sent, the
     * isolated map size, the round cap, the commander experience granted on a
     * win, and (via child tables) the enemy fleets and item prizes.
     */
    public function up(): void
    {
        Schema::create('investigation_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();

            // How many of the player's fleets may be dispatched into a run.
            $table->unsignedTinyInteger('min_player_fleets')->default(1);
            $table->unsignedTinyInteger('max_player_fleets')->default(1);

            // Isolated battle map size (generic units; fleets step 10 at a time
            // and occupy a Fleet::SIZE footprint).
            $table->unsignedInteger('map_width')->default(200);
            $table->unsignedInteger('map_height')->default(200);

            // Hard cap on rounds; if nobody is wiped out by then the run
            // expires with no winner (default 30 per the design).
            $table->unsignedSmallInteger('max_rounds')->default(30);

            // Commander experience awarded to every participating commander on
            // a win (split/shared rules live in the service).
            $table->unsignedInteger('exp_reward')->default(0);

            // Whether the investigation is currently offered to players.
            $table->boolean('is_active')->default(true);

            // Optional UI hints.
            $table->string('image_url')->nullable();
            $table->string('color')->nullable();

            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investigation_definitions');
    }
};
