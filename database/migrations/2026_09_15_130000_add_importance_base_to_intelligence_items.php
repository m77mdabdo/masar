<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Splits importance into the part that keeps and the part that decays.
 *
 * `importance` is computed from four inputs, one of which is recency measured
 * against `now()`. Storing that produces a number that is wrong the moment it
 * is written and more wrong every hour after — a third of the corpus drifted in
 * a single day, and the inbox's "most important first" ordering quietly stopped
 * meaning anything because the recency weight varied only by how stale a feed's
 * own listing was.
 *
 * `importance_base` holds the three durable inputs — source trust, document
 * type, entity match — already weighted. Recency is added back at read time,
 * and in SQL when ordering, so the ranking is correct whenever it is asked
 * rather than whenever it was written.
 *
 * `importance` stays, and keeps its old meaning: what we thought at ingest.
 * That is worth keeping for audit — it is the number an editor saw — and
 * renaming a column in place would have made every historical row lie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intelligence_items', function (Blueprint $table): void {
            $table->unsignedSmallInteger('importance_base')->nullable()->after('importance');

            // The inbox orders by the live score, which is this column plus a
            // recency term computed in SQL.
            $table->index(['review_state', 'importance_base'], 'intel_state_base_index');
        });
    }

    public function down(): void
    {
        Schema::table('intelligence_items', function (Blueprint $table): void {
            $table->dropIndex('intel_state_base_index');
            $table->dropColumn('importance_base');
        });
    }
};
