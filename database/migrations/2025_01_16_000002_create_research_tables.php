<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Research catalog + per-account progress. Research is unlocked by the
     * "Centro de Pesquisa" and comes in two flavours:
     *
     *  - technology: has a configurable max level, can depend on one or more
     *    other technologies (at a minimum level), costs gold and takes time.
     *    At most one technology can be in progress at a time per player.
     *
     *  - plant: always a single level. Requires a plant blueprint (an item of
     *    type 'plant') to be present in the inventory, and may consume extra
     *    inventory items when started. Costs gold and takes time.
     */
    public function up(): void
    {
        Schema::create('research_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            // 'technology' | 'plant'
            $table->string('type')->default('technology');
            // Gold is always the currency for research.
            $table->unsignedBigInteger('gold_cost')->default(0);
            // Base wait time in seconds (before the Research Center reduction).
            $table->unsignedInteger('research_time')->default(60);
            // Technologies can have >1 level; plants are always max_level = 1.
            $table->unsignedInteger('max_level')->default(1);
            // Geometric growth applied to gold_cost/research_time per level for
            // technologies (1.0 = flat). Ignored for single-level plants.
            $table->decimal('gold_cost_growth', 8, 3)->default(1.000);
            $table->decimal('research_time_growth', 8, 3)->default(1.000);
            // For plant research: the plant blueprint item required in the
            // inventory to unlock it. NULL for technologies.
            $table->string('required_item_key')->nullable();
            // Whether the required plant blueprint is consumed on research.
            $table->boolean('consumes_required_item')->default(false);
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->timestamps();

            $table->index('type');
        });

        // Technology dependency edges: a research requires another research to
        // be at least `min_level` before it can be started.
        Schema::create('research_definition_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_definition_id')->constrained('research_definitions')->cascadeOnDelete();
            $table->foreignId('depends_on_id')->constrained('research_definitions')->cascadeOnDelete();
            $table->unsignedInteger('min_level')->default(1);
            $table->timestamps();

            $table->unique(['research_definition_id', 'depends_on_id'], 'research_dep_unique');
        });

        // Extra inventory items consumed to start a research (in addition to,
        // and including, the plant blueprint when it is flagged as consumed).
        Schema::create('research_definition_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_definition_id')->constrained('research_definitions')->cascadeOnDelete();
            // References the item catalog by its stable key.
            $table->string('item_key');
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();

            $table->unique(['research_definition_id', 'item_key'], 'research_item_unique');
        });

        // A player's progress on a research. One row per (user, research).
        // `level` = completed level (0 = not yet researched). An in-progress
        // research is tracked with target_level/started_at/finish_at.
        Schema::create('player_research', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('research_definition_id')->constrained('research_definitions')->cascadeOnDelete();
            $table->unsignedInteger('level')->default(0);
            // In-progress research (only one active per player, enforced in code).
            $table->unsignedInteger('target_level')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finish_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'research_definition_id'], 'player_research_unique');
            $table->index('finish_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_research');
        Schema::dropIfExists('research_definition_items');
        Schema::dropIfExists('research_definition_dependencies');
        Schema::dropIfExists('research_definitions');
    }
};
