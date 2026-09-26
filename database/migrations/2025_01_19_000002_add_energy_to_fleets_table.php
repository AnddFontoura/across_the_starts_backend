<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A fleet stores an amount of energy in its tank. The tank capacity is
     * derived from its composition (sum of each ship's energy_capacity times
     * its quantity, base + modules) and is NOT persisted here — only the
     * current stored amount is.
     *
     * A fleet with energy = 0 is "stranded": it can't move or attack (but can
     * still be attacked). Fueling debits the shared energy resource pool.
     */
    public function up(): void
    {
        Schema::table('fleets', function (Blueprint $table) {
            $table->unsignedBigInteger('energy')->default(0)->after('y');
        });
    }

    public function down(): void
    {
        Schema::table('fleets', function (Blueprint $table) {
            $table->dropColumn('energy');
        });
    }
};
