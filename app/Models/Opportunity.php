<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OpportunityPotential;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Opportunity extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'opportunities';

    protected $fillable = [
        'locale',
        'translation_group_id',
        'slug',
        'title',
        'summary',
        'opportunity_type',
        'industry_id',
        'country_id',
        'potential',
        'requirements',
        'deadline',
        'official_source_url',
        'status',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'potential' => OpportunityPotential::class,
            'requirements' => 'array',
            'deadline' => 'date',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $opportunity): void {
            $opportunity->translation_group_id ??= (string) Str::uuid();
        });
    }

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function mentions(): MorphMany
    {
        return $this->morphMany(EntityMention::class, 'mentionable');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function scopeForLocale(Builder $query, string $locale): Builder
    {
        return $query->where('locale', $locale);
    }
}
