<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A commander instance in a player's pool (max 30 per account). Has a
     * commander rank (I..X) and a proficiency ranking (I..V) for each of the
     * four ship classes and three weapon types.
     */
    public function up(): void
    {
        Schema::create('commanders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commander_definition_id')->constrained('commander_definitions')->cascadeOnDelete();
            $table->string('name');

            // Commander rank: I..X (stored as 1..10). Simple commanders = 1.
            $table->unsignedTinyInteger('rank')->default(1);

            // Ship-class proficiencies: I..V (stored 1..5).
            $table->unsignedTinyInteger('prof_cruiser')->default(1);
            $table->unsignedTinyInteger('prof_battleship')->default(1);
            $table->unsignedTinyInteger('prof_frigate')->default(1);
            $table->unsignedTinyInteger('prof_fighter')->default(1);

            // Weapon-type proficiencies: I..V (stored 1..5).
            $table->unsignedTinyInteger('prof_machinegun')->default(1);
            $table->unsignedTinyInteger('prof_laser')->default(1);
            $table->unsignedTinyInteger('prof_missile')->default(1);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commanders');
    }
};
