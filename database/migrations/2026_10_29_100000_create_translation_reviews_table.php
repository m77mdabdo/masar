<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Whether a person has read each interface translation file, kept apart from
 * whether the edition is public (MASAR_LOCALES_ENABLED) and from whether any
 * article is published (the editorial workflow). Owner's ruling, 2026-10-29:
 * an edition may be public while its interface text is still pending review;
 * what it may not do is claim a review nobody made.
 *
 * One row per locale and file. A file whose content hash changes after it was
 * approved goes back to pending_review and records when (invalidated_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translation_reviews', function (Blueprint $table): void {
            $table->id();
            $table->string('locale', 10);
            $table->string('path', 191);
            $table->char('content_hash', 64);
            $table->unsignedInteger('total_strings')->default(0);
            $table->unsignedInteger('reviewed_strings')->default(0);
            $table->unsignedInteger('pending_strings')->default(0);
            $table->string('status', 32)->default('pending_review');
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('invalidated_at')->nullable();
            $table->timestamp('file_modified_at')->nullable();
            $table->timestamps();

            $table->unique(['locale', 'path']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_reviews');
    }
};
