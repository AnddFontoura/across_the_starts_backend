<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional artwork for a research definition. Each research (technology or
     * plant) has ONE image, used across all of its levels. Technologies show a
     * golden overlay on top (in the UI) that intensifies with the researched
     * level. Until an image is set, the UI shows a placeholder.
     */
    public function up(): void
    {
        Schema::table('research_definitions', function (Blueprint $table) {
            $table->string('image_url')->nullable()->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('research_definitions', function (Blueprint $table) {
            $table->dropColumn('image_url');
        });
    }
};
