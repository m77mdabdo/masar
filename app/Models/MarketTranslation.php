<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketTranslation extends Model
{
    public $timestamps = false;

    protected $table = 'market_translations';

    protected $fillable = [
        'market_id',
        'locale',
        'name',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Market::class, 'market_id');
    }
}
