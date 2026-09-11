<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Co-authors. `articles.author_id` remains the lead byline; this table holds
 * everyone else who earned a credit, with an optional role (reporting, data,
 * photography, translation).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_author', function (Blueprint $table): void {
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->nullable();

            $table->primary(['article_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_author');
    }
};
