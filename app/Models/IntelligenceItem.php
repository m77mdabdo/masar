<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ClassificationPath;
use App\Enums\DocumentType;
use App\Enums\DuplicateStage;
use App\Enums\ReviewState;
use Illuminate\Database\Eloquent\Builder;
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
    public function scopeQueued(Builder $query): void
    {
        $query->where('review_state', ReviewState::New->value)
            ->orderByDesc('importance')
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
