<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ClassificationPath;
use App\Enums\DocumentType;
use App\Enums\DuplicateStage;
use App\Enums\ReviewState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row in the editorial inbox.
 *
 * Everything on it is either a fact about the publication or our own writing.
 * The publisher's prose lives on `SourceItem` and is purged on a schedule; this
 * record survives, which is what makes retention a one-line update.
 */
class IntelligenceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_item_id',
        'source_id',
        'title',
        'summary',
        'url',
        'published_at',
        'detected_at',
        'locale',
        'document_type',
        'classification_path',
        'classification_evidence',
        'importance',
        'importance_base',
        'importance_inputs',
        'review_state',
        'reviewed_by_id',
        'reviewed_at',
        'duplicate_of_id',
        'duplicate_stage',
        'article_id',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'classification_path' => ClassificationPath::class,
            'duplicate_stage' => DuplicateStage::class,
            'review_state' => ReviewState::class,
            'classification_evidence' => 'array',
            'importance_inputs' => 'array',
            'importance' => 'integer',
            'importance_base' => 'integer',
            'published_at' => 'datetime',
            'detected_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function sourceItem(): BelongsTo
    {
        return $this->belongsTo(SourceItem::class);
    }

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    /**
     * The queue: anything still untriaged, most important first.
     *
     * @param  Builder<self>  $query
     */
    /**
     * The score as it stands now, not as it stood at ingest.
     *
     * `importance_base` holds the three inputs that keep — source trust,
     * document type, entity match. Recency is the one that decays, so it is
     * added here against the clock rather than frozen into a column. An item
     * ingested fresh and read three days later should not still rank as fresh.
     */
    public function liveImportance(?Carbon $now = null): int
    {
        return (int) round($this->liveImportanceScore($now));
    }

    /**
     * The same score, unrounded — the value to compare on.
     *
     * `liveImportance()` rounds for display, and rounding before comparing
     * reorders near-ties: two items at 67.9 and 67.6 both read as 68 and then
     * sort by whatever the tiebreak says, which is not the order SQL produces.
     * Anything ranking in PHP must use this; the inbox ranks in SQL and uses
     * the same arithmetic.
     */
    public function liveImportanceScore(?Carbon $now = null): float
    {
        $base = $this->importance_base;

        if ($base === null) {
            // Never re-derived since the split. Honest fallback: the stored
            // snapshot, which is what it always was.
            return (float) $this->importance;
        }

        $weight = (float) config('masar.intelligence.importance.weights.recency', 0.15);
        $hours = (int) config('masar.intelligence.importance.recency_hours', 48);

        $recency = 0.5; // No publication date is "unknown", not "old".

        if ($this->published_at !== null) {
            /*
             * Whole minutes, and one clock.
             *
             * SQL truncates with TIMESTAMPDIFF(MINUTE, ...), so fractional
             * minutes here put the two arithmetics microscopically out of step
             * — enough to reorder exact ties. And calling now() per item makes
             * a PHP-side sort non-deterministic against itself, because items
             * compared microseconds apart get different ages. Both are invisible
             * until something depends on the order.
             */
            $age = (int) max(0, floor($this->published_at->diffInMinutes($now ?? now())));
            $recency = max(0.0, 1 - ($age / max(1, $hours * 60)));
        }

        return $base + ($recency * $weight * 100);
    }

    /**
     * Order by the live score, in SQL.
     *
     * An accessor cannot appear in ORDER BY, and paginating on a PHP-sorted
     * collection would sort one page at a time — which looks right and is not.
     * So the same arithmetic is expressed in SQL: the durable base plus a
     * recency term measured against NOW().
     *
     * @param  Builder<self>  $query
     */
    public function scopeOrderByLiveImportance(Builder $query, string $direction = 'desc'): void
    {
        $weight = (float) config('masar.intelligence.importance.weights.recency', 0.15);
        $minutes = max(1, (int) config('masar.intelligence.importance.recency_hours', 48) * 60);

        $query->orderByRaw(
            'COALESCE(importance_base, importance) + ('
            .'CASE WHEN published_at IS NULL THEN 0.5 '
            .'ELSE GREATEST(0, 1 - (TIMESTAMPDIFF(MINUTE, published_at, NOW()) / ?)) END'
            .') * ? * 100 '.($direction === 'asc' ? 'asc' : 'desc'),
            [$minutes, $weight],
        );

        /*
         * Ties are common — two items from the same source with the same
         * document type and the same publication hour score identically — and
         * an unordered tie is not a cosmetic problem. Pagination on a
         * non-deterministic sort can show the same row on two pages and skip
         * another entirely, which reads as items vanishing from the inbox.
         */
        $query->orderByDesc('detected_at')->orderByDesc('id');
    }

    public function scopeQueued(Builder $query): void
    {
        $query->where('review_state', ReviewState::New->value)
            ->orderByLiveImportance()
            ->orderByDesc('detected_at');
    }

    /**
     * Dismissals past the recovery window. Purged, so a wrong call at 9am is
     * reversible for a month and not forever.
     *
     * @param  Builder<self>  $query
     */
    public function scopePurgeable(Builder $query): void
    {
        $days = (int) config('masar.intelligence.retention.dismissed_days', 30);

        $query->whereIn('review_state', [ReviewState::Rejected->value, ReviewState::Ignored->value])
            ->whereNotNull('reviewed_at')
            ->where('reviewed_at', '<=', now()->subDays($days));
    }
}
