<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ArticleSection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleBlock extends Model
{
    use HasFactory;

    protected $fillable = [
        'article_id',
        'type',
        'section',
        'data',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'section' => ArticleSection::class,
            'sort_order' => 'integer',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /**
     * Blocks that answer one of the four questions, in document order.
     *
     * @param  Builder<self>  $query
     */
    public function scopeSectioned(Builder $query): void
    {
        $query->whereNotNull('section');
    }
}
