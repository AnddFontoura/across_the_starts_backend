<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A player's custom ship design (blueprint): a named model built on a base
     * aircraft type and filled with modules. Aircraft are later built FROM a
     * design. Designs belong to the user (account-level, like the fleet).
     */
    public function up(): void
    {
        Schema::create('ship_designs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('aircraft_type_id')->constrained('aircraft_types')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ship_designs');
    }
};
