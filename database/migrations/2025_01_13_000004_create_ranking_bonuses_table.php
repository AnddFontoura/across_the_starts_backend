<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin-configurable bonuses per ranking level. Two scopes:
     *  - 'proficiency': ship-class / weapon proficiency levels I..V.
     *  - 'commander':   overall commander rank I..X.
     * Bonuses are percentages applied to attack and defense (hull+shield).
     */
    public function up(): void
    {
        Schema::create('ranking_bonuses', function (Blueprint $table) {
            $table->id();
            $table->string('scope'); // proficiency | commander
            $table->unsignedTinyInteger('level'); // 1..5 for proficiency, 1..10 for commander
            $table->integer('attack_percent')->default(0);
            $table->integer('defense_percent')->default(0); // applies to hull + shield
            $table->timestamps();

            $table->unique(['scope', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ranking_bonuses');
    }
};
