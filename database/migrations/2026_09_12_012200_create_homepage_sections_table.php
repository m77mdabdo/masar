<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `source` decides who fills the section:
 *   manual — an editor pinned specific content
 *   auto   — a query defined in `config`
 *   mixed  — pinned items first, then the query fills the remainder
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homepage_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('layout_id')->constrained('homepage_layouts')->cascadeOnDelete();
            $table->string('type');
            $table->json('title')->nullable();
            $table->string('source')->default('auto');
            $table->json('config');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->index(['layout_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_sections');
    }
};
