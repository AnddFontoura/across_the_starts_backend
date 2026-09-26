<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Item prizes granted on completing (winning) an investigation. Prizes are
     * items only (no direct resources); one of the items may itself be a
     * "random resource box" whose contents are resolved on use (defined later).
     *
     * Each row grants `quantity` of an item, optionally with a drop `chance`
     * (percent, 1..100) so prizes can be guaranteed or random.
     */
    public function up(): void
    {
        Schema::create('investigation_prizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investigation_definition_id')
                ->constrained('investigation_definitions')
                ->cascadeOnDelete();

            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();

            $table->unsignedInteger('quantity')->default(1);

            // Drop chance in percent (100 = guaranteed).
            $table->unsignedTinyInteger('chance')->default(100);

            $table->timestamps();

            $table->index('investigation_definition_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investigation_prizes');
    }
};
