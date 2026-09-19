<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('structures', function (Blueprint $table) {
            $table->unsignedInteger('level')->default(1)->after('structure_type_id');

            // Construction / upgrade state.
            // is_constructed = false while the initial build is in progress.
            // busy_until = timestamp when the current build/upgrade finishes.
            // pending_level = the level the structure becomes when an in-progress
            //                 upgrade completes (null when not upgrading).
            $table->boolean('is_constructed')->default(true)->after('level');
            $table->timestamp('busy_until')->nullable()->after('is_constructed');
            $table->unsignedInteger('pending_level')->nullable()->after('busy_until');
        });
    }

    public function down(): void
    {
        Schema::table('structures', function (Blueprint $table) {
            $table->dropColumn(['level', 'is_constructed', 'busy_until', 'pending_level']);
        });
    }
};
