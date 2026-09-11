<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The source of truth for article body content.
 *
 * `type` is a plain string rather than a database enum: block types are a
 * rendering concern and new ones ship with a Blade component, not a migration.
 * Valid values: paragraph, heading, image, quote, pullquote, numbers, table,
 * embed, video, callout, opportunity, explainer, divider.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->json('data');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['article_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_blocks');
    }
};
