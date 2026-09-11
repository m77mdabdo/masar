<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class MenuItem extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = [
        'menu_id',
        'parent_id',
        'label',
        'url',
        'linkable_type',
        'linkable_id',
        'sort_order',
        'is_active',
        'show_desktop',
        'show_mobile',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'label' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'show_desktop' => 'boolean',
            'show_mobile' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    public function label(?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();
        $labels = $this->label ?? [];

        return $labels[$locale] ?? $labels[config('masar.default_locale', 'ar')] ?? null;
    }

    /**
     * Active now: flagged active and inside its scheduling window, if it has one.
     */
    public function scopeCurrentlyVisible($query)
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['menu_id', 'parent_id', 'label', 'url', 'linkable_type', 'linkable_id', 'is_active', 'sort_order'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
