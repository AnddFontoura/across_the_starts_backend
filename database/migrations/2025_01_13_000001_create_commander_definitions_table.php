<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Commander templates. The generic recruitable "Comandante" plus future
     * custom commanders (from events/purchases) added to a player's pool.
     */
    public function up(): void
    {
        Schema::create('commander_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color', 20)->default('#c9a24b');
            $table->string('image_url')->nullable();
            // Whether this definition is the one produced by hourly recruitment.
            $table->boolean('is_recruitable')->default(false);
            // Highest commander rank (I..X) instances of this definition may reach.
            $table->unsignedTinyInteger('max_rank')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commander_definitions');
    }
};
