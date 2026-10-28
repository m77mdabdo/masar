<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A translation is an article row of its own in the source's translation
 * group (CLAUDE.md §4: one row per locale). These columns record where it
 * came from and how far it has got.
 *
 * - source_article_id: the version it was translated from. NULL is an
 *   original, written in its own language.
 * - translation_status: the pipeline's state (App\Enums\TranslationStatus).
 *   Publication is not here; it stays ArticleStatus, behind the same gate.
 * - source_hash: the source's translatable content when this was produced.
 *   A different hash today means the translation is out of date.
 *
 * (translation_group_id, locale) becomes unique: two French versions of one
 * story is the duplicate the brief forbids. It covers translation_group_id as
 * a left prefix, so the old single-column index goes (CLAUDE.md §6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->foreignId('source_article_id')->nullable()->after('translation_group_id')
                ->constrained('articles')->nullOnDelete();
            $table->string('translation_status', 32)->nullable()->after('source_article_id');
            $table->string('translation_origin', 32)->nullable()->after('translation_status');
            $table->char('source_hash', 64)->nullable()->after('translation_origin');
            $table->text('translation_error')->nullable()->after('source_hash');
            $table->unsignedTinyInteger('translation_attempts')->default(0)->after('translation_error');
            $table->timestamp('translated_at')->nullable()->after('translation_attempts');
            $table->foreignId('translation_reviewed_by_id')->nullable()->after('translated_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('translation_reviewed_at')->nullable()->after('translation_reviewed_by_id');

            $table->unique(['translation_group_id', 'locale']);
            $table->dropIndex(['translation_group_id']);
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->index('translation_group_id');
            $table->dropUnique(['translation_group_id', 'locale']);
            $table->dropConstrainedForeignId('translation_reviewed_by_id');
            $table->dropConstrainedForeignId('source_article_id');
            $table->dropColumn(['translation_status', 'translation_origin', 'source_hash', 'translation_error', 'translation_attempts', 'translated_at', 'translation_reviewed_at']);
        });
    }
};
