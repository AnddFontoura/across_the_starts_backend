<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catalog of aircraft types (cruiser, battleship, frigate, fighter).
     * Aircraft are flat units (no levels). They deal no innate damage — damage
     * comes from modules (a future feature). Special bonuses live in a JSON
     * column so modules/bonuses can be added without schema changes.
     */
    public function up(): void
    {
        Schema::create('aircraft_types', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // cruiser | battleship | frigate | fighter
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color', 20)->default('#8e7cc3');

            // Module storage space (used by a future modules feature).
            $table->unsignedInteger('storage')->default(0);

            // Build cost — any combination of the three resources (0 = unused).
            $table->unsignedBigInteger('cost_gold')->default(0);
            $table->unsignedBigInteger('cost_metal')->default(0);
            $table->unsignedBigInteger('cost_energy')->default(0);

            // Build time in seconds (before the hangar's reduction).
            $table->unsignedInteger('build_time')->default(0);

            // Combat/movement attributes (0 or N).
            $table->unsignedBigInteger('shield')->default(0);
            $table->unsignedBigInteger('hull')->default(0);   // "estrutura"
            $table->unsignedInteger('movement')->default(0);

            // Free-form special attributes / module bonuses.
            $table->json('special_attributes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aircraft_types');
    }
};
