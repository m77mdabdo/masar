<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Country extends Model
{
    use HasFactory;
    use HasTranslations;

    protected $table = 'countries';

    protected $fillable = [
        'slug',
        'code',
    ];

    /**
     * Used by the opportunities filter to offer only options that have results.
     */
    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class, 'country_id');
    }

    /**
     * Every piece of content that references this entity, across all content types.
     */
    public function mentions(): MorphMany
    {
        return $this->morphMany(EntityMention::class, 'entity');
    }
}
