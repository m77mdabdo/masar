<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryTranslation extends Model
{
    public $timestamps = false;

    protected $table = 'category_translations';

    protected $fillable = [
        'category_id',
        'locale',
        'name',
        'description',
        'meta_title',
        'meta_description',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
}
