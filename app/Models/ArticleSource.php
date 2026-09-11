<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'article_id',
        'title',
        'url',
        'publisher',
        'source_type',
        'accessed_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'accessed_at' => 'date',
            'sort_order' => 'integer',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
