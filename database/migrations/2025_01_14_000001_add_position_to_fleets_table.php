<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fleets are shown on the planetary base map as a 10x10 marker the player
     * can move freely. Store their top-left position in generic terrain units.
     */
    public function up(): void
    {
        Schema::table('fleets', function (Blueprint $table) {
            $table->unsignedInteger('x')->default(0)->after('name');
            $table->unsignedInteger('y')->default(0)->after('x');
        });
    }

    public function down(): void
    {
        Schema::table('fleets', function (Blueprint $table) {
            $table->dropColumn(['x', 'y']);
        });
    }
};
