<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndustryTranslation extends Model
{
    public $timestamps = false;

    protected $table = 'industry_translations';

    protected $fillable = [
        'industry_id',
        'locale',
        'name',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Industry::class, 'industry_id');
    }
}
