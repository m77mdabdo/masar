<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdCreative extends Model
{
    use HasFactory;

    protected $fillable = [
        'ad_campaign_id',
        'ad_placement_id',
        'image_path',
        'alt',
        'destination_url',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'impressions' => 'integer',
            'clicks' => 'integer',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class, 'ad_campaign_id');
    }

    public function placement(): BelongsTo
    {
        return $this->belongsTo(AdPlacement::class, 'ad_placement_id');
    }

    /**
     * Servable: active itself, in an active placement, on a running campaign.
     *
     * All three conditions in SQL. A creative whose campaign ended yesterday is
     * not a row the template has to remember to check.
     *
     * @param  Builder<self>  $query
     */
    public function scopeServable(Builder $query): void
    {
        $query->where('is_active', true)
            ->whereHas('campaign', fn (Builder $q) => $q->running())
            ->whereHas('placement', fn (Builder $q) => $q->where('is_active', true));
    }
}
