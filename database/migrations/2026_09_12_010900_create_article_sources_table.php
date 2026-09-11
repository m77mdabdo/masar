<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The trust layer. Every published article carries at least one source.
 *
 * `source_type`: official_document, press_release, interview, report, data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('url')->nullable();
            $table->string('publisher')->nullable();
            $table->string('source_type');
            $table->date('accessed_at')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['article_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_sources');
    }
};
