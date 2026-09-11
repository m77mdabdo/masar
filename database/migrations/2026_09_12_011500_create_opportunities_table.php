<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The third step of the reader journey made into its own content type.
 *
 * `official_source_url` is not decoration: an opportunity without a verifiable
 * official source is a rumour, and MASAR does not publish rumours.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunities', function (Blueprint $table): void {
            $table->id();
            $table->char('locale', 2)->default('ar');
            $table->uuid('translation_group_id');
            $table->string('slug');
            $table->string('title');
            $table->text('summary');
            $table->string('opportunity_type');
            $table->foreignId('industry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('potential', ['high', 'medium', 'low'])->default('medium');
            $table->json('requirements')->nullable();
            $table->date('deadline')->nullable();
            $table->string('official_source_url')->nullable();
            $table->string('status')->default('open');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['locale', 'slug']);
            $table->index(['potential', 'status', 'published_at']);
            $table->index('translation_group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunities');
    }
};
