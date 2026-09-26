<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds combat attributes to commanders. Each commander has four attributes
     * that influence the damage its fleet deals and takes: pontaria (aim),
     * desvio (evasion), critico (crit) and velocidade (speed).
     *
     * An attribute's effective value grows with the commander's level:
     *     effective = base + (level - 1) * (natural_growth_per_level + growth_factor)
     * capped at LEVEL_MAX (50). The base and the definition-wide
     * natural_growth_per_level live on the definition; the per-commander
     * growth factors (rolled at recruitment) and the current level live on the
     * commander instance.
     *
     * Growth factors are decimals in [0, 10] with the constraint that the four
     * factors of a single commander never sum to more than 20. They are rolled
     * once at recruitment and can be re-rolled by the "Pergaminho do Caminho".
     */
    public function up(): void
    {
        Schema::table('commander_definitions', function (Blueprint $table) {
            // How many attribute points a commander of this definition gains per
            // level, before the per-attribute growth factor is added. Simple
            // commanders = 1.
            $table->unsignedTinyInteger('natural_growth_per_level')->default(1)->after('max_rank');

            // Base attribute values (level 1) for commanders of this definition.
            $table->unsignedSmallInteger('base_pontaria')->default(0)->after('natural_growth_per_level');
            $table->unsignedSmallInteger('base_desvio')->default(0)->after('base_pontaria');
            $table->unsignedSmallInteger('base_critico')->default(0)->after('base_desvio');
            $table->unsignedSmallInteger('base_velocidade')->default(0)->after('base_critico');
        });

        Schema::table('commanders', function (Blueprint $table) {
            // Current commander level (1..50). Attributes scale with it.
            $table->unsignedSmallInteger('level')->default(1)->after('rank');

            // Per-commander growth factors, decimals in [0, 10]. Rolled at
            // recruitment so the four of them sum to at most 20. Re-rollable.
            $table->decimal('growth_pontaria', 4, 2)->default(0)->after('level');
            $table->decimal('growth_desvio', 4, 2)->default(0)->after('growth_pontaria');
            $table->decimal('growth_critico', 4, 2)->default(0)->after('growth_desvio');
            $table->decimal('growth_velocidade', 4, 2)->default(0)->after('growth_critico');
        });
    }

    public function down(): void
    {
        Schema::table('commanders', function (Blueprint $table) {
            $table->dropColumn([
                'level',
                'growth_pontaria',
                'growth_desvio',
                'growth_critico',
                'growth_velocidade',
            ]);
        });

        Schema::table('commander_definitions', function (Blueprint $table) {
            $table->dropColumn([
                'natural_growth_per_level',
                'base_pontaria',
                'base_desvio',
                'base_critico',
                'base_velocidade',
            ]);
        });
    }
};
