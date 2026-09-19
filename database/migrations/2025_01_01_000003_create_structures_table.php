<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A structure instance placed on a base at position (x, y).
     * Position and footprint are in generic terrain units.
     */
    public function up(): void
    {
        Schema::create('structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('base_id')->constrained()->cascadeOnDelete();
            $table->foreignId('structure_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('x'); // top-left corner, generic units
            $table->unsignedInteger('y');
            $table->timestamp('last_collected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('structures');
    }
};
