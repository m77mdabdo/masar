<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TopicTranslation extends Model
{
    public $timestamps = false;

    protected $table = 'topic_translations';

    protected $fillable = [
        'topic_id',
        'locale',
        'name',
        'description',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Topic::class, 'topic_id');
    }
}
