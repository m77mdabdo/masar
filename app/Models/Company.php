<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CompanyType;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Company extends Model
{
    use HasFactory;
    use HasTranslations;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'slug',
        'type',
        'industry_id',
        'country_id',
        'founded_year',
        'website',
        'ticker',
        'logo_path',
        'is_verified',
        'mentions_count',
    ];

    protected function casts(): array
    {
        return [
            'type' => CompanyType::class,
            'founded_year' => 'integer',
            'is_verified' => 'boolean',
            'mentions_count' => 'integer',
        ];
    }

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function people(): HasMany
    {
        return $this->hasMany(Person::class);
    }

    /**
     * Every piece of content referencing this company, across all content types.
     * This is the query the company profile page is built on.
     */
    public function mentions(): MorphMany
    {
        return $this->morphMany(EntityMention::class, 'entity');
    }

    public function scopeStartups($query)
    {
        return $query->where('type', CompanyType::Startup);
    }

    public function scopeOfType($query, CompanyType $type)
    {
        return $query->where('type', $type);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['slug', 'type', 'industry_id', 'country_id', 'ticker', 'is_verified'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
