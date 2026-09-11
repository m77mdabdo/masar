<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Topic extends Model
{
    use HasFactory;
    use HasTranslations;

    protected $fillable = [
        'slug',
        'is_featured',
        'articles_count',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'articles_count' => 'integer',
        ];
    }

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_topic');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }
}
