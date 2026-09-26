<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Item catalog + per-account ownership.
     *
     * `items` is the catalog: every item that can exist in the game (name,
     * type, effects and stack limit). No items are seeded yet.
     *
     * `player_items` is a player's ownership row: one row per (user, item) =
     * one occupied inventory slot (a single stack). The stack `quantity` is
     * bounded by the item's `max_stack` (e.g. 9999 or 1 for unique items).
     */
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            // 'consumable' | 'plant' (blueprints/schematics for research).
            $table->string('type')->default('consumable');
            // Structured, per-item effects (kept as JSON for flexibility).
            $table->json('effects')->nullable();
            // Maximum amount that can be held in a single stack/slot.
            // 1 = unique (one per player); e.g. 9999 for common consumables.
            $table->unsignedInteger('max_stack')->default(9999);
            // Optional UI hint (icon key / color).
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->timestamps();

            $table->index('type');
        });

        Schema::create('player_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();

            // One stack row per (user, item): a player holds a single stack of
            // a given item. This makes "1 slot = 1 stack" trivially true.
            $table->unique(['user_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_items');
        Schema::dropIfExists('items');
    }
};
