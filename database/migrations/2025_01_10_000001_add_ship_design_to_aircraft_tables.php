<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The fleet is now built from custom ship designs (blueprints) rather than
     * raw aircraft types. Ownership and the build queue are keyed by
     * ship_design_id; aircraft_type_id is kept (nullable) as a convenience
     * reference to the design's base hull.
     */
    public function up(): void
    {
        // --- aircraft ---
        // Add the new design key + its unique FIRST. The new unique
        // (user_id, ship_design_id) also starts with user_id, so it can back
        // the existing user_id foreign key once the old composite unique is
        // dropped (MySQL requires an index covering an FK column at all times).
        Schema::table('aircraft', function (Blueprint $table) {
            $table->unsignedBigInteger('aircraft_type_id')->nullable()->change();
            $table->foreignId('ship_design_id')->nullable()->after('user_id')
                ->constrained('ship_designs')->cascadeOnDelete();
            $table->unique(['user_id', 'ship_design_id']);
        });

        $this->dropForeignIfExists('aircraft', 'aircraft_type_id');
        Schema::table('aircraft', function (Blueprint $table) {
            $table->dropUnique('aircraft_user_id_aircraft_type_id_unique');
        });

        // --- aircraft_build_orders ---
        $this->dropForeignIfExists('aircraft_build_orders', 'aircraft_type_id');
        Schema::table('aircraft_build_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('aircraft_type_id')->nullable()->change();
            $table->foreign('aircraft_type_id')->references('id')->on('aircraft_types')->cascadeOnDelete();
            $table->foreignId('ship_design_id')->nullable()->after('user_id')
                ->constrained('ship_designs')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('aircraft', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'ship_design_id']);
            $table->dropConstrainedForeignId('ship_design_id');
            $table->unique(['user_id', 'aircraft_type_id']);
        });

        Schema::table('aircraft_build_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ship_design_id');
        });
    }

    /**
     * Drop a foreign key on the given column only if it currently exists.
     */
    private function dropForeignIfExists(string $table, string $column): void
    {
        $rows = DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE '
            .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? '
            .'AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$table, $column]
        );

        foreach ($rows as $row) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$row->CONSTRAINT_NAME}`");
        }
    }
};
