<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A player now has more than one base: the "terrestrial" resource base and
     * the "planetary" defense base (in orbit). Each base is unique per
     * (user, kind), so the previous unique-per-user constraint is replaced.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('bases', 'kind')) {
            Schema::table('bases', function (Blueprint $table) {
                // terrestrial (default, existing bases) | planetary (orbital defense)
                $table->string('kind')->default('terrestrial')->after('user_id');
            });
        }

        // Backfill existing rows explicitly (default already handles it, but be
        // safe for databases that don't apply defaults retroactively).
        \Illuminate\Support\Facades\DB::table('bases')->whereNull('kind')->update(['kind' => 'terrestrial']);

        Schema::table('bases', function (Blueprint $table) {
            // The user_id foreign key relies on the single-column unique index,
            // so drop the FK first, swap the unique for a (user, kind) one,
            // then restore the FK.
            $table->dropForeign(['user_id']);
            $table->dropUnique('bases_user_id_unique');
            $table->unique(['user_id', 'kind']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bases', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique('bases_user_id_kind_unique');
            $table->unique('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->dropColumn('kind');
        });
    }
};
