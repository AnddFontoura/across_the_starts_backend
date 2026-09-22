<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Placed structures track their current hit points. Combat isn't wired up
     * yet, but the column lets a structure be damaged and repaired later.
     * null = "full" (lazily treated as the level's max HP).
     */
    public function up(): void
    {
        Schema::table('structures', function (Blueprint $table) {
            $table->unsignedBigInteger('current_hp')->nullable()->after('pending_level');
        });
    }

    public function down(): void
    {
        Schema::table('structures', function (Blueprint $table) {
            $table->dropColumn('current_hp');
        });
    }
};
