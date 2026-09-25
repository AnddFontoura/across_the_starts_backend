<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catalog of ship modules. Any module may grant any of the attributes
     * (0 when it doesn't). Attack modules also have a weapon type and a range.
     * Modules occupy `space` on a ship and may add build time.
     */
    public function up(): void
    {
        Schema::create('module_types', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color', 20)->default('#8e7cc3');

            // Attributes (any may be 0).
            $table->unsignedInteger('movement')->default(0);
            $table->unsignedBigInteger('attack')->default(0);
            $table->unsignedBigInteger('hull')->default(0);    // "estrutura"
            $table->unsignedBigInteger('shield')->default(0);
            $table->unsignedInteger('space')->default(1);      // space occupied on the ship

            // Weapon type (only meaningful when attack > 0). null = not a weapon.
            $table->string('attack_type')->nullable(); // machinegun | laser | missile
            $table->unsignedInteger('range')->default(0); // weapon reach

            // Extra build time (seconds) this module adds to the ship.
            $table->unsignedInteger('build_time_add')->default(0);

            // Build cost (any combination of the three resources; 0 = unused).
            $table->unsignedBigInteger('cost_gold')->default(0);
            $table->unsignedBigInteger('cost_metal')->default(0);
            $table->unsignedBigInteger('cost_energy')->default(0);

            // Free-form special attributes / bonuses.
            $table->json('special_attributes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_types');
    }
};
