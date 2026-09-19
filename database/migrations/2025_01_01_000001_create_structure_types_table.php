<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catalog of structures a player can build.
     * Sizes (width/height) are in the game's generic terrain units.
     */
    public function up(): void
    {
        Schema::create('structure_types', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();          // machine key, e.g. "gold_mine"
            $table->string('name');                    // display name
            $table->text('description')->nullable();
            $table->unsignedInteger('width')->default(20);   // generic units
            $table->unsignedInteger('height')->default(20);  // generic units
            $table->string('resource');                // gold | metal | energy
            $table->unsignedInteger('production_per_hour')->default(0);
            $table->string('color')->default('#888888'); // UI hint
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('structure_types');
    }
};
