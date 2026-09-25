<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow per-level overrides for combat stats too: HP, damage and range.
     * A non-null value here wins over the type's formula for that level.
     */
    public function up(): void
    {
        Schema::table('structure_level_configs', function (Blueprint $table) {
            $table->unsignedBigInteger('hp')->nullable()->after('upgrade_time');
            $table->unsignedBigInteger('damage')->nullable()->after('hp');
            $table->unsignedInteger('range')->nullable()->after('damage'); // in cells
        });
    }

    public function down(): void
    {
        Schema::table('structure_level_configs', function (Blueprint $table) {
            $table->dropColumn(['hp', 'damage', 'range']);
        });
    }
};
