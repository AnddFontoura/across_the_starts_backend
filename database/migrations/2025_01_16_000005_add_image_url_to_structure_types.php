<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional artwork for a structure type, shown in the build catalog and
     * on placed structures. Until an image is set, the UI falls back to the
     * structure's color swatch.
     */
    public function up(): void
    {
        Schema::table('structure_types', function (Blueprint $table) {
            $table->string('image_url')->nullable()->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('structure_types', function (Blueprint $table) {
            $table->dropColumn('image_url');
        });
    }
};
