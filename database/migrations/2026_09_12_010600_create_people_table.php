<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Real people only.
 *
 * `is_expert = true` marks someone quotable in an Expert Insight block. Everyone
 * flagged here must be a real, identifiable person who has consented to being
 * quoted by MASAR. Never seed, invent or infer an expert: a fabricated quote
 * attributed to a named person is the single most damaging thing this platform
 * could publish.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('photo_path')->nullable();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('linkedin')->nullable();
            $table->boolean('is_expert')->default(false);
            $table->integer('mentions_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_expert', 'company_id']);
        });

        Schema::create('person_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->char('locale', 2);
            $table->string('name');
            $table->string('title')->nullable();
            $table->text('bio')->nullable();

            $table->unique(['person_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_translations');
        Schema::dropIfExists('people');
    }
};
