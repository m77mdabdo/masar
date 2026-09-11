<?php

declare(strict_types=1);

use App\Enums\ArticleStatus;
use App\Enums\ContentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The centre of the content graph.
 *
 * `why_it_matters`, `business_impact` and `opportunity` are first-class columns,
 * not body text, because the journey Information → Understanding → Opportunity is
 * a data model. Making them optional prose would make the product a blog.
 *
 * Editorial content is one row per locale linked by `translation_group_id`: an
 * English version of an Arabic story is rarely a literal translation, and often
 * never written at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table): void {
            $table->id();

            $table->char('locale', 2)->default('ar');
            $table->uuid('translation_group_id');

            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('editor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('fact_checker_id')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('status', array_column(ArticleStatus::cases(), 'value'))
                ->default(ArticleStatus::Idea->value);
            $table->enum('content_type', array_column(ContentType::cases(), 'value'))
                ->default(ContentType::News->value);

            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('slug');

            // The 30-second summary: an array of 2-5 plain strings.
            $table->json('summary')->nullable();

            // Rendered fallback only. article_blocks is the source of truth.
            $table->longText('body')->nullable();

            $table->text('why_it_matters')->nullable();
            $table->text('business_impact')->nullable();
            $table->text('opportunity')->nullable();

            // [{value, label}]
            $table->json('key_numbers')->nullable();

            $table->foreignId('hero_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('hero_alt')->nullable();
            $table->string('hero_credit')->nullable();

            $table->smallInteger('reading_time')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_sponsored')->default(false);
            $table->string('sponsor_name')->nullable();

            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->string('canonical_url')->nullable();
            $table->string('og_image_path')->nullable();
            $table->boolean('noindex')->default(false);

            // {linkedin, x, instagram, newsletter, push}
            $table->json('distribution')->nullable();

            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('updated_content_at')->nullable();
            $table->timestamp('fact_checked_at')->nullable();

            // Flushed periodically from Redis. Never incremented per request.
            $table->integer('views_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            // A slug is unique within a locale, never globally: /ar/x and /en/x
            // are different articles, not a collision.
            $table->unique(['locale', 'slug']);

            $table->index('status');
            $table->index('published_at');
            $table->index(['status', 'published_at']);
            $table->index(['category_id', 'status', 'published_at']);
            $table->index(['locale', 'status', 'published_at']);
            $table->index('translation_group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
