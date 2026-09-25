<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A pending commander recruitment. Only one runs at a time per player and
     * takes one hour. When finishes_at passes, it settles into a new commander
     * with randomly rolled proficiencies.
     */
    public function up(): void
    {
        Schema::create('commander_recruitments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commander_definition_id')->constrained('commander_definitions')->cascadeOnDelete();
            $table->timestamp('finishes_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commander_recruitments');
    }
};
