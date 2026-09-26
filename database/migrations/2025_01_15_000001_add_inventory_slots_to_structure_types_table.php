<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inventory structures (the "Forte Protetor") act as the planet owner's
     * item box. They grant the player a number of inventory slots that scales
     * with level:
     *
     *     slots(level) = inventory_slots_base + inventory_slots_per_level * (level - 1)
     *
     * e.g. base 30, +2 per level: level 1 = 30, level 30 = 88.
     *
     * A "slot" holds a single stack of one item; the stack size is bounded by
     * the item's own max_stack.
     */
    public function up(): void
    {
        Schema::table('structure_types', function (Blueprint $table) {
            $table->unsignedInteger('inventory_slots_base')->default(0)->after('fleet_capacity_growth');
            $table->unsignedInteger('inventory_slots_per_level')->default(0)->after('inventory_slots_base');
        });
    }

    public function down(): void
    {
        Schema::table('structure_types', function (Blueprint $table) {
            $table->dropColumn([
                'inventory_slots_base',
                'inventory_slots_per_level',
            ]);
        });
    }
};
