<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The raw envelope, exactly as a publisher gave it to us.
 *
 * Internal for its whole life. CLAUDE.md §5: raw text ingested from monitored
 * sources never renders on the public site. Nothing in `app/Http/Controllers/Web`
 * may read this table, and a test asserts it.
 *
 * Split from `intelligence_items` so retention is a single, obvious operation:
 * `raw_body` is nulled on a schedule and the processed record — which is our own
 * writing — survives untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();

            // The publisher's own id for the entry, where the feed gives one.
            // Stage three of dedup.
            $table->string('external_id')->nullable();

            $table->text('url');
            $table->text('canonical_url')->nullable();

            $table->string('raw_title', 512);
            $table->text('raw_summary')->nullable();

            // Only ever populated where the source's legal_mode permits it, and
            // purged after the retention window.
            $table->longText('raw_body')->nullable();

            $table->string('author')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('fetched_at');

            // Stage one and two of dedup are exact matches on a hash of the
            // normalised URL — indexable, unlike the URL text itself.
            $table->char('url_hash', 64);
            $table->char('canonical_hash', 64)->nullable();

            // 64-bit SimHash of the normalised title, stored as hex so it is
            // portable and readable. Stage four compares Hamming distance.
            $table->char('title_simhash', 16)->nullable();

            // The untouched payload, for debugging a parser without re-fetching.
            $table->json('payload')->nullable();

            $table->timestamps();

            // Dedup stages one to three, and the retention sweep.
            $table->index(['source_id', 'url_hash']);
            $table->index('canonical_hash');
            $table->index(['source_id', 'external_id']);
            $table->index('fetched_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_items');
    }
};
