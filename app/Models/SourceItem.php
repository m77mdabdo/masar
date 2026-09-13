<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * The raw envelope from a publisher. Internal for its whole life.
 *
 * CLAUDE.md §5: raw text ingested from monitored sources never renders on the
 * public site. Nothing under `app/Http/Controllers/Web` may touch this model,
 * and a test asserts it.
 */
class SourceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_id',
        'external_id',
        'url',
        'canonical_url',
        'raw_title',
        'raw_summary',
        'raw_body',
        'author',
        'published_at',
        'fetched_at',
        'url_hash',
        'canonical_hash',
        'title_simhash',
        'payload',
    ];

    /**
     * Hidden from every array and JSON cast of this model.
     *
     * Belt and braces against the failure that matters: a publisher's body text
     * reaching a response because somebody returned the model rather than a
     * field of it.
     */
    protected $hidden = ['raw_body', 'payload'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'published_at' => 'datetime',
            'fetched_at' => 'datetime',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function intelligenceItem(): HasOne
    {
        return $this->hasOne(IntelligenceItem::class);
    }
}
