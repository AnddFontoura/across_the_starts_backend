<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A player's terrain. Dimensions are in generic units and customizable.
     * Holds the player's resource balances.
     */
    public function up(): void
    {
        Schema::create('bases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('width')->default(1000);   // generic units
            $table->unsignedInteger('height')->default(1000);  // generic units
            $table->unsignedBigInteger('gold')->default(0);
            $table->unsignedBigInteger('metal')->default(0);
            $table->unsignedBigInteger('energy')->default(0);

            // Lifetime totals of resources ever collected (never decrease).
            $table->unsignedBigInteger('total_gold_collected')->default(0);
            $table->unsignedBigInteger('total_metal_collected')->default(0);
            $table->unsignedBigInteger('total_energy_collected')->default(0);

            $table->timestamps();

            $table->unique('user_id'); // one base per player for now
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bases');
    }
};
