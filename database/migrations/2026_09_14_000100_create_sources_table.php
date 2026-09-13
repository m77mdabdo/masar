<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The things we watch.
 *
 * Most of the columns here are health, not configuration, and that is
 * deliberate: a source that has quietly stopped returning items is the failure
 * this whole subsystem is built to avoid, so its state is a first-class part of
 * the row rather than something reconstructed from logs.
 *
 * `type` and `legal_mode` are `string(32)`, never a native ENUM — CLAUDE.md §6.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table): void {
            $table->id();

            $table->string('name');
            $table->string('url');
            $table->string('feed_url')->nullable();
            $table->string('type', 32);
            $table->string('category', 64)->nullable();
            $table->char('locale', 2)->default('ar');

            // 1-5. Feeds into the importance score, and it is a judgement about
            // the publisher, not about any single item.
            $table->unsignedTinyInteger('trust_level')->default(3);

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('poll_frequency_minutes')->default(30);

            // Per-source parser hints: JSON pointers for an API, an element
            // name for an unusual feed. Shape depends on `type`.
            $table->json('parser_config')->nullable();

            // Conditional GET. Sending these back is the difference between
            // polling a publisher politely and hammering them.
            $table->string('etag')->nullable();
            $table->string('last_modified')->nullable();

            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_item_at')->nullable();

            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->text('error_message')->nullable();

            // What we may keep. Defaults to the restrictive mode — the one that
            // needs nobody's permission.
            $table->string('legal_mode', 32)->default('metadata');

            $table->timestamps();

            // The scheduler's only question: which active sources are due?
            $table->index(['is_active', 'last_checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};
