<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A named slot in the design. Templates ask for a placement by key. */
class AdPlacement extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'name', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function creatives(): HasMany
    {
        return $this->hasMany(AdCreative::class);
    }
}
