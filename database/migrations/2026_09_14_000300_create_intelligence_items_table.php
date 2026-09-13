<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The processed record an editor actually works with.
 *
 * Everything here is either a fact about the publication (a link, a date) or
 * our own writing (`summary`). The publisher's prose stays in `source_items`
 * and is purged; this row is what survives, which is why the retention job can
 * be a one-line update rather than a cascade.
 *
 * `importance_inputs` exists so the score is arguable. A number an editor
 * cannot interrogate is a number they will learn to ignore.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intelligence_items', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('source_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();

            $table->string('title', 512);

            // Ours, not theirs. Generated internally; never a copy of the feed's
            // description where the source's legal_mode forbids retention.
            $table->text('summary')->nullable();

            $table->text('url');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('detected_at');
            $table->char('locale', 2)->default('ar');

            $table->string('document_type', 32)->default('other');
            $table->string('classification_path', 32)->default('unclassified');
            $table->json('classification_evidence')->nullable();

            $table->unsignedTinyInteger('importance')->default(0);
            $table->json('importance_inputs')->nullable();

            $table->string('review_state', 32)->default('new');
            $table->foreignId('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            // Flagged, never auto-discarded: a second development on the same
            // story is often the actual news. The editor decides.
            $table->foreignId('duplicate_of_id')->nullable()
                ->constrained('intelligence_items')->nullOnDelete();
            $table->string('duplicate_stage', 32)->nullable();

            // Set when an editor turns an item into a draft. The link is what
            // lets us answer "did monitoring earn its keep this month".
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();

            // The inbox's default view: unresolved, most important first.
            $table->index(['review_state', 'importance', 'detected_at']);
            // Per-source filtering, and the health panel's seven-day count.
            $table->index(['source_id', 'detected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intelligence_items');
    }
};
