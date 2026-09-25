<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modules installed on a ship design, with a quantity per module type.
     * The same module may be repeated (quantity), but a design may contain at
     * most one weapon attack_type (enforced in the service, not the schema).
     */
    public function up(): void
    {
        Schema::create('ship_design_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ship_design_id')->constrained('ship_designs')->cascadeOnDelete();
            $table->foreignId('module_type_id')->constrained('module_types')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();

            $table->unique(['ship_design_id', 'module_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ship_design_modules');
    }
};
