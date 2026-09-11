<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CountryTranslation extends Model
{
    public $timestamps = false;

    protected $table = 'country_translations';

    protected $fillable = [
        'country_id',
        'locale',
        'name',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }
}
