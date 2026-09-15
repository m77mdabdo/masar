<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\IllustrativeMediaRejected;
use Illuminate\Database\Eloquent\Builder;
use Spatie\MediaLibrary\MediaCollections\Models\Media as BaseMedia;

/**
 * Our media record.
 *
 * Extends Spatie's so `is_illustrative` is a first-class, queryable property
 * rather than a key inside `custom_properties` — the difference matters because
 * the restriction is enforced by a query in the article hero picker, and you
 * cannot reliably filter a JSON key across the row set.
 */
class Media extends BaseMedia
{
    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'is_illustrative' => 'boolean',
        ]);
    }

    /**
     * An illustrative image may not belong to editorial content at all.
     *
     * Enforced on the record rather than only at the pickers, because there are
     * several ways a media row acquires an owner — an upload, the library
     * seeder, a future import — and a guard on one of them is a guard on none.
     */
    protected static function booted(): void
    {
        static::saving(function (self $media): void {
            if ($media->is_illustrative && $media->model_type === 'article') {
                throw IllustrativeMediaRejected::forArticle();
            }
        });
    }

    /**
     * Images that may stand in for reality: a real photograph of a real thing.
     *
     * @param  Builder<self>  $query
     */
    public function scopeReportage(Builder $query): void
    {
        $query->where('is_illustrative', false);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeIllustrative(Builder $query): void
    {
        $query->where('is_illustrative', true);
    }
}
