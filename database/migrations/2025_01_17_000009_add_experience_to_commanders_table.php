<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Commander experience. Winning an investigation grants exp to every
     * participating commander; accumulated exp raises the commander's level
     * (capped at LEVEL_MAX), which in turn raises the effective attributes that
     * already scale off level. The exp->level curve lives in the Commander
     * model so balancing is centralized.
     */
    public function up(): void
    {
        Schema::table('commanders', function (Blueprint $table) {
            $table->unsignedBigInteger('experience')->default(0)->after('level');
        });
    }

    public function down(): void
    {
        Schema::table('commanders', function (Blueprint $table) {
            $table->dropColumn('experience');
        });
    }
};
