<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional per-level overrides. When a row exists for a (type, level),
     * its non-null values take precedence over the formula on the type.
     * This lets an admin hand-tune specific levels.
     */
    public function up(): void
    {
        Schema::create('structure_level_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('level');
            $table->unsignedInteger('production_per_hour')->nullable();
            $table->unsignedBigInteger('max_capacity')->nullable();
            $table->unsignedBigInteger('protection')->nullable();
            $table->unsignedBigInteger('upgrade_cost_gold')->nullable();
            $table->unsignedBigInteger('upgrade_cost_metal')->nullable();
            $table->unsignedBigInteger('upgrade_cost_energy')->nullable();
            $table->unsignedInteger('upgrade_time')->nullable(); // seconds
            $table->timestamps();

            $table->unique(['structure_type_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('structure_level_configs');
    }
};
