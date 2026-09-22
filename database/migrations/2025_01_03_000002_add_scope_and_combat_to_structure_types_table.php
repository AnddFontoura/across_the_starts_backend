<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Structure types now belong to a base "scope": terrestrial (resource
     * economy) or planetary (orbital defense). Planetary structures also have
     * combat stats: hit points (HP) and damage, both scaling with level.
     */
    public function up(): void
    {
        Schema::table('structure_types', function (Blueprint $table) {
            // Which base this type can be built on.
            $table->string('scope')->default('terrestrial')->after('category'); // terrestrial | planetary

            // Hit points scaling (all planetary structures have HP).
            $table->unsignedBigInteger('hp_base')->default(0)->after('protection_growth');
            $table->decimal('hp_growth', 6, 3)->default(1.200)->after('hp_base');

            // Damage scaling (defense structures that attack invaders).
            $table->unsignedBigInteger('damage_base')->default(0)->after('hp_growth');
            $table->decimal('damage_growth', 6, 3)->default(1.200)->after('damage_base');
        });
    }

    public function down(): void
    {
        Schema::table('structure_types', function (Blueprint $table) {
            $table->dropColumn(['scope', 'hp_base', 'hp_growth', 'damage_base', 'damage_growth']);
        });
    }
};
