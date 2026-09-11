<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonTranslation extends Model
{
    public $timestamps = false;

    protected $table = 'person_translations';

    protected $fillable = [
        'person_id',
        'locale',
        'name',
        'title',
        'bio',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id');
    }
}
