<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscriber extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'locale',
        'status',
        'verified_at',
        'preferences',
        'visitor_id',
        'source',
        'unsubscribed_at',
    ];

    protected function casts(): array
    {
        return [
            'preferences' => 'array',
            'verified_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    protected $hidden = [
        'visitor_id',
    ];

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed')
            ->whereNotNull('verified_at')
            ->whereNull('unsubscribed_at');
    }
}
