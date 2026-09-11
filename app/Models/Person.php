<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Real, identifiable people only. See the migration note on `is_expert`.
 */
class Person extends Model
{
    use HasFactory;
    use HasTranslations;
    use SoftDeletes;

    protected $table = 'people';

    protected string $translationForeignKey = 'person_id';

    protected $fillable = [
        'slug',
        'photo_path',
        'company_id',
        'linkedin',
        'is_expert',
        'mentions_count',
    ];

    protected function casts(): array
    {
        return [
            'is_expert' => 'boolean',
            'mentions_count' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function mentions(): MorphMany
    {
        return $this->morphMany(EntityMention::class, 'entity');
    }

    public function scopeExperts($query)
    {
        return $query->where('is_expert', true);
    }
}
