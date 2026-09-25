<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional artwork for aircraft types and modules. Until real images are
     * uploaded, the UI shows a photo-emoji placeholder when this is null.
     */
    public function up(): void
    {
        Schema::table('aircraft_types', function (Blueprint $table) {
            $table->string('image_url')->nullable()->after('color');
        });
        Schema::table('module_types', function (Blueprint $table) {
            $table->string('image_url')->nullable()->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('aircraft_types', function (Blueprint $table) {
            $table->dropColumn('image_url');
        });
        Schema::table('module_types', function (Blueprint $table) {
            $table->dropColumn('image_url');
        });
    }
};
