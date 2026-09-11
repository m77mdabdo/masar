<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topics', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->boolean('is_featured')->default(false);

            // Materialised counter. A topic listing must never run COUNT() per row.
            $table->integer('articles_count')->default(0);
            $table->timestamps();

            $table->index(['is_featured', 'articles_count']);
        });

        Schema::create('topic_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->char('locale', 2);
            $table->string('name');
            $table->text('description')->nullable();

            $table->unique(['topic_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topic_translations');
        Schema::dropIfExists('topics');
    }
};
