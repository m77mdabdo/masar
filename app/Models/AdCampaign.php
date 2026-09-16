<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdCampaign extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'advertiser', 'starts_at', 'ends_at', 'is_active'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function creatives(): HasMany
    {
        return $this->hasMany(AdCreative::class);
    }

    /**
     * Live right now — flagged active and inside its dates.
     *
     * A null start is "already running" and a null end is "until switched off".
     * The dates are checked in SQL rather than after loading, so an expired
     * campaign is never a row we have to remember to skip.
     *
     * @param  Builder<self>  $query
     */
    public function scopeRunning(Builder $query): void
    {
        $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    public function isRunning(): bool
    {
        return $this->is_active
            && ($this->starts_at === null || $this->starts_at->lte(now()))
            && ($this->ends_at === null || $this->ends_at->gte(now()));
    }
}
