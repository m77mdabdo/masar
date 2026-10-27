<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the stored reading time. It is computed from the body instead.
 *
 * `PublishArticle` froze the estimate onto the row at publish, and the stored
 * value then won over the accessor — so an article edited after publication
 * kept the old figure until someone republished it, on all ten surfaces that
 * show it including every card.
 *
 * Recomputing on save would have worked until the next code path that writes an
 * article without passing through it, which is exactly how this bug existed: the
 * accessor was always correct and the column overrode it.
 *
 * A computed value cannot drift. Ten surfaces reading an accessor is cheaper
 * than ten surfaces reading a column that lies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropColumn('reading_time');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->smallInteger('reading_time')->nullable()->after('body');
        });
    }
};
