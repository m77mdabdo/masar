<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Real readership, by day.
 *
 * `articles.views_count` existed and was written by nothing: the homepage's
 * most-read section and the admin widget both ranked on a number only the
 * seeder had ever set, so «الأكثر قراءة» meant "whatever the seeder wrote".
 *
 * Per-day rows rather than one lifetime total, because most-read on a news site
 * means this week. A lifetime counter ossifies — the same five stories win
 * forever and the section stops changing, which is the opposite of what it is
 * for. `views_count` stays as the lifetime total; ranking reads this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->date('viewed_on');
            $table->unsignedInteger('views')->default(0);
            $table->timestamps();

            // One row per article per day; the flush upserts into it.
            $table->unique(['article_id', 'viewed_on']);

            // The ranking query: a date range, summed and ordered.
            $table->index(['viewed_on', 'article_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_views');
    }
};
