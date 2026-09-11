<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomepageSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'layout_id',
        'type',
        'title',
        'source',
        'config',
        'sort_order',
        'is_visible',
    ];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'config' => 'array',
            'sort_order' => 'integer',
            'is_visible' => 'boolean',
        ];
    }

    public function layout(): BelongsTo
    {
        return $this->belongsTo(HomepageLayout::class, 'layout_id');
    }

    public function title(?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();
        $titles = $this->title ?? [];

        return $titles[$locale] ?? $titles[config('masar.default_locale', 'ar')] ?? null;
    }
}
