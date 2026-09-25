<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aircraft now have a CLASS (cruiser | battleship | frigate | fighter)
     * separate from the individual ship. There will be many ships per class.
     * The counter matrix is between CLASSES (4x4), not individual ships, so
     * aircraft_matchups moves from type ids to class strings.
     */
    public function up(): void
    {
        Schema::table('aircraft_types', function (Blueprint $table) {
            $table->string('class')->default('cruiser')->after('key'); // cruiser|battleship|frigate|fighter
        });

        // Backfill class from the existing example ships' keys where they match
        // a class name (the 4 seeded ships used the class as their key).
        foreach (['cruiser', 'battleship', 'frigate', 'fighter'] as $class) {
            \Illuminate\Support\Facades\DB::table('aircraft_types')
                ->where('key', $class)
                ->update(['class' => $class]);
        }

        // Rebuild the matchup table as class-vs-class. Old per-ship rows are
        // discarded (dev data); classes are reseeded by the seeder.
        Schema::dropIfExists('aircraft_matchups');
        Schema::create('aircraft_matchups', function (Blueprint $table) {
            $table->id();
            $table->string('attacker_class');
            $table->string('defender_class');
            $table->integer('damage_bonus_percent')->default(0);
            $table->integer('damage_reduction_percent')->default(0);
            $table->timestamps();

            $table->unique(['attacker_class', 'defender_class'], 'aircraft_matchup_class_pair_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aircraft_matchups');
        Schema::create('aircraft_matchups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attacker_type_id')->constrained('aircraft_types')->cascadeOnDelete();
            $table->foreignId('defender_type_id')->constrained('aircraft_types')->cascadeOnDelete();
            $table->integer('damage_bonus_percent')->default(0);
            $table->integer('damage_reduction_percent')->default(0);
            $table->timestamps();
            $table->unique(['attacker_type_id', 'defender_type_id'], 'aircraft_matchup_pair_unique');
        });

        Schema::table('aircraft_types', function (Blueprint $table) {
            $table->dropColumn('class');
        });
    }
};
