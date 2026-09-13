<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotFound extends Model
{
    use HasFactory;

    protected $table = 'not_founds';

    protected $fillable = [
        'path',
        'referrer',
        'hits',
        'first_seen_at',
        'last_seen_at',
        'resolved',
    ];

    protected function casts(): array
    {
        return [
            'hits' => 'integer',
            'resolved' => 'boolean',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function scopeUnresolved($query)
    {
        return $query->where('resolved', false);
    }
}
