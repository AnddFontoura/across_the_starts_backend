<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A stack of identical ships within a battle fleet. Holds the per-ship
     * combat stats (already including all bonuses) plus the pooled hp of the
     * stack, so losses can be applied as whole ships destroyed. On the player
     * side `ship_design_id` lets end-of-battle settlement decrement the correct
     * Aircraft inventory row (destroyed ships are gone permanently).
     */
    public function up(): void
    {
        Schema::create('battle_ships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('battle_fleet_id')
                ->constrained('battle_fleets')
                ->cascadeOnDelete();

            // Player-side stacks map back to a ship design for settlement.
            $table->foreignId('ship_design_id')->nullable()
                ->constrained('ship_designs')->nullOnDelete();
            $table->foreignId('aircraft_type_id')
                ->constrained('aircraft_types')->cascadeOnDelete();

            $table->string('name')->nullable();
            $table->string('ship_class'); // cruiser|battleship|frigate|fighter
            $table->string('weapon_type')->nullable(); // machinegun|laser|missile|null

            // Per-ship stats (with all commander/research bonuses folded in).
            $table->unsignedInteger('attack')->default(0);
            $table->unsignedInteger('hull')->default(1);
            $table->unsignedInteger('shield')->default(0);
            $table->unsignedInteger('weapon_range')->default(0);
            $table->unsignedInteger('movement')->default(0);

            // How many ships started in this stack and how many remain.
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('quantity_remaining')->default(0);

            // Pooled hp of the surviving ships in the stack. When it drops below
            // one ship's worth, that ship is destroyed (quantity_remaining--).
            $table->bigInteger('hp_remaining')->default(0);

            $table->timestamps();

            $table->index('battle_fleet_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('battle_ships');
    }
};
