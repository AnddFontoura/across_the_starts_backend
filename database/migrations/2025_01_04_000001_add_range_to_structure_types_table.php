<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Defense structures gain an attack "range" (alcance), measured in cells,
     * where 1 cell = 10x10 generic terrain units. Range scales with level like
     * the other combat stats (base + growth).
     */
    public function up(): void
    {
        Schema::table('structure_types', function (Blueprint $table) {
            // Attack range in cells (1 cell = 10x10 units). 0 = melee/no reach.
            $table->unsignedInteger('range_base')->default(0)->after('damage_growth');
            $table->decimal('range_growth', 6, 3)->default(1.000)->after('range_base');
        });
    }

    public function down(): void
    {
        Schema::table('structure_types', function (Blueprint $table) {
            $table->dropColumn(['range_base', 'range_growth']);
        });
    }
};
