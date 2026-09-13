<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\Articles\EvaluatePublishGate;
use App\Enums\ArticleStatus;
use App\Enums\ContentType;
use App\Support\MediaConversions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Article extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'locale',
        'translation_group_id',
        'category_id',
        'author_id',
        'editor_id',
        'fact_checker_id',
        'scheduled_by_id',
        'status',
        'content_type',
        'title',
        'subtitle',
        'slug',
        'summary',
        'body',
        'what_happened',
        'why_it_matters',
        'business_impact',
        'opportunity',
        'key_numbers',
        'hero_media_id',
        'hero_alt',
        'hero_credit',
        'reading_time',
        'is_featured',
        'is_sponsored',
        'sponsor_name',
        'meta_title',
        'meta_description',
        'canonical_url',
        'og_image_path',
        'noindex',
        'distribution',
        'published_at',
        'scheduled_for',
        'updated_content_at',
        'fact_checked_at',
        'views_count',
        'gate_failures_count',
    ];

    protected function casts(): array
    {
        return [
            'status' => ArticleStatus::class,
            'content_type' => ContentType::class,
            'summary' => 'array',
            'key_numbers' => 'array',
            'distribution' => 'array',
            'is_featured' => 'boolean',
            'is_sponsored' => 'boolean',
            'noindex' => 'boolean',
            'reading_time' => 'integer',
            'views_count' => 'integer',
            'gate_failures_count' => 'integer',
            'published_at' => 'datetime',
            'scheduled_for' => 'datetime',
            'updated_content_at' => 'datetime',
            'fact_checked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Every article belongs to a translation group, even a group of one.
        // Without this, the first translation added has nothing to join against.
        static::creating(function (self $article): void {
            $article->translation_group_id ??= (string) Str::uuid();
        });
    }

    // ------------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------------

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'editor_id');
    }

    public function factChecker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fact_checker_id');
    }

    public function scheduledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_by_id');
    }

    /**
     * One hero image, replaced rather than accumulated. `hero_media_id` mirrors
     * this collection so the publish gate and every listing query can ask about
     * the hero with a column read instead of a media lookup.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('hero')
            ->singleFile()
            ->acceptsMimeTypes(config('masar.media.accepted'));

        // Images used inside the body. Separate from `hero` because the hero is
        // one image the gate asks about and these are many the editor places.
        $this->addMediaCollection('inline')
            ->acceptsMimeTypes(config('masar.media.accepted'));

        // Self-hosted video and its poster are two records, not one file the
        // browser has to open before it can show anything: the poster is a real
        // image with real conversions, which is what lets <video preload="none">
        // cost a thumbnail instead of a download.
        $this->addMediaCollection('video')
            ->singleFile()
            ->acceptsMimeTypes(config('masar.media.accepted_video'));

        $this->addMediaCollection('video_poster')
            ->singleFile()
            ->acceptsMimeTypes(config('masar.media.accepted'));
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        MediaConversions::register($this, 'hero', 'inline', 'video_poster');
    }

    public function heroMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'hero_media_id');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(ArticleBlock::class)->orderBy('sort_order');
    }

    public function sources(): HasMany
    {
        return $this->hasMany(ArticleSource::class)->orderBy('sort_order');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ArticleRevision::class)->latest('created_at');
    }

    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class, 'article_topic');
    }

    public function coAuthors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'article_author')
            ->withPivot('role');
    }

    public function related(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'article_related', 'article_id', 'related_article_id')
            ->withPivot('sort_order')
            ->orderBy('article_related.sort_order');
    }

    public function mentions(): MorphMany
    {
        return $this->morphMany(EntityMention::class, 'mentionable');
    }

    /**
     * The other language versions of this story. Excludes itself.
     */
    public function translations(): Builder
    {
        return static::query()
            ->where('translation_group_id', $this->translation_group_id)
            ->whereKeyNot($this->getKey());
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    /**
     * Live content only. Status alone is not enough: a scheduled article flipped
     * to `published` with a future `published_at` must stay invisible.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ArticleStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeForLocale(Builder $query, string $locale): Builder
    {
        return $query->where('locale', $locale);
    }

    // ------------------------------------------------------------------
    // Derived values
    // ------------------------------------------------------------------

    /**
     * Stored `reading_time` wins when set; otherwise estimate from the body.
     * Arabic reads slower than English — see config('masar.reading_speed_wpm').
     */
    public function getReadingTimeAttribute($value): int
    {
        if ($value !== null && (int) $value > 0) {
            return (int) $value;
        }

        // Whitespace splitting, not str_word_count: the latter is Latin-centric
        // and returns 0 for Arabic text.
        $text = trim(strip_tags((string) $this->body));
        $words = $text === ''
            ? 0
            : count(preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: []);

        if ($words === 0) {
            return 1;
        }

        $wpm = max(1, (int) config('masar.reading_speed_wpm', 180));

        return max(1, (int) ceil($words / $wpm));
    }

    // ------------------------------------------------------------------
    // Publish gate
    // ------------------------------------------------------------------

    /**
     * Thin delegate so Filament, the model and the Action all read one gate.
     *
     * @return array<int, string> empty means publishable
     */
    public function isPublishable(): array
    {
        return app(EvaluatePublishGate::class)($this);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'status', 'title', 'slug', 'category_id', 'author_id', 'editor_id',
                'fact_checker_id', 'published_at', 'scheduled_for', 'fact_checked_at',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
