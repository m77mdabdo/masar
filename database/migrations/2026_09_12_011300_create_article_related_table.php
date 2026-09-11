<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Editor-curated related reading. Deliberately directional: A pointing at B does
 * not imply B points back at A, because relevance is rarely symmetrical.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_related', function (Blueprint $table): void {
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('related_article_id')->constrained('articles')->cascadeOnDelete();
            $table->integer('sort_order')->default(0);

            $table->primary(['article_id', 'related_article_id']);
            $table->index('related_article_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_related');
    }
};
