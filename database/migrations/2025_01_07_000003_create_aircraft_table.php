<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A player's fleet: how many completed aircraft of each type the account
     * owns. Aircraft belong to the USER (not a base) because they can be sent
     * to attack other planets. One row per (user, type).
     */
    public function up(): void
    {
        Schema::create('aircraft', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('aircraft_type_id')->constrained('aircraft_types')->cascadeOnDelete();
            $table->unsignedBigInteger('quantity')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'aircraft_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aircraft');
    }
};
