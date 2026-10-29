<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TranslationReviewStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TranslationReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'locale',
        'path',
        'content_hash',
        'total_strings',
        'reviewed_strings',
        'pending_strings',
        'status',
        'reviewer_id',
        'reviewed_at',
        'invalidated_at',
        'file_modified_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TranslationReviewStatus::class,
            'total_strings' => 'integer',
            'reviewed_strings' => 'integer',
            'pending_strings' => 'integer',
            'reviewed_at' => 'datetime',
            'invalidated_at' => 'datetime',
            'file_modified_at' => 'datetime',
        ];
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
