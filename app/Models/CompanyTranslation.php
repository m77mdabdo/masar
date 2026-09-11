<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyTranslation extends Model
{
    public $timestamps = false;

    protected $table = 'company_translations';

    protected $fillable = [
        'company_id',
        'locale',
        'name',
        'short_description',
        'description',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
