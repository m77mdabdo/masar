<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\MediaConversions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

class HomepageLayout extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $fillable = [
        'name',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * The backdrop behind the big story.
     *
     * It belongs to the layout and not to the article, and that is the whole
     * point: the big story's own photograph still illustrates the article page
     * and every card. This is page furniture — mood behind a headline — so it
     * is the one place an illustrative image is allowed to live.
     */
    public function registerMediaCollections(): void
    {
        // Not single-file: the layout may hold several backdrops, and the
        // lead slide shows one of them per page load (backdrop()).
        $this->addMediaCollection('backdrop')
            ->acceptsMimeTypes(config('masar.media.accepted'));
    }

    public function registerMediaConversions(?SpatieMedia $media = null): void
    {
        MediaConversions::register($this, 'backdrop');
    }

    /**
     * One of the layout's backdrops, chosen per page load.
     *
     * Called once per request — home.blade.php hands the same instance to the
     * LCP preload and to the big story — so the preloaded file is always the
     * one painted.
     */
    public function backdrop(): ?Media
    {
        $backdrops = $this->getMedia('backdrop');

        /** @var Media|null $media */
        $media = $backdrops->isEmpty() ? null : $backdrops->random();

        return $media;
    }

    public function sections(): HasMany
    {
        return $this->hasMany(HomepageSection::class, 'layout_id')->orderBy('sort_order');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }
}
