<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SourceLegalMode;
use App\Enums\SourceType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A feed we watch.
 *
 * The health columns are the point of this model. A monitoring system that
 * silently stops monitoring is the worst failure available to it — nobody
 * notices the story we never saw — so "is this source still working" is
 * answerable from the row itself, not reconstructed from logs.
 */
class Source extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = [
        'name',
        'url',
        'feed_url',
        'type',
        'category',
        'locale',
        'trust_level',
        'is_active',
        'poll_frequency_minutes',
        'parser_config',
        'legal_mode',
    ];

    protected function casts(): array
    {
        return [
            'type' => SourceType::class,
            'legal_mode' => SourceLegalMode::class,
            'parser_config' => 'array',
            'is_active' => 'boolean',
            'trust_level' => 'integer',
            'poll_frequency_minutes' => 'integer',
            'consecutive_failures' => 'integer',
            'last_checked_at' => 'datetime',
            'last_success_at' => 'datetime',
            'last_item_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SourceItem::class);
    }

    public function intelligenceItems(): HasMany
    {
        return $this->hasMany(IntelligenceItem::class);
    }

    /**
     * The URL we actually poll. Falls back to the site URL for sitemaps and
     * APIs that have no separate feed address.
     */
    public function pollUrl(): string
    {
        return $this->feed_url ?: $this->url;
    }

    /**
     * Sources that are due.
     *
     * A source that has never been checked is always due. Backoff is applied by
     * pushing `last_checked_at` forward on failure rather than by storing a
     * separate "next run" column, so there is one clock and not two.
     *
     * @param  Builder<self>  $query
     */
    public function scopeDue(Builder $query): void
    {
        $query->where('is_active', true)->where(function (Builder $q): void {
            $q->whereNull('last_checked_at')
                ->orWhereRaw('last_checked_at <= DATE_SUB(NOW(), INTERVAL poll_frequency_minutes MINUTE)');
        });
    }

    /**
     * Health, as a word. Rendered in the resource so a failing source is
     * visible without reading two timestamps and doing arithmetic.
     */
    public function healthState(): string
    {
        if (! $this->is_active) {
            return $this->consecutive_failures >= $this->failureThreshold() ? 'disabled' : 'paused';
        }

        if ($this->consecutive_failures > 0) {
            return 'failing';
        }

        if ($this->last_success_at === null) {
            return 'unchecked';
        }

        // Working, but producing nothing. A feed that returns 200 and no new
        // items for a week has usually moved, and looks perfectly healthy by
        // every other measure.
        if ($this->last_item_at !== null && $this->last_item_at->diffInDays(now()) > 7) {
            return 'quiet';
        }

        return 'healthy';
    }

    public function failureThreshold(): int
    {
        return (int) config('masar.intelligence.failure_threshold', 5);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'feed_url', 'is_active', 'trust_level', 'poll_frequency_minutes', 'legal_mode'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
