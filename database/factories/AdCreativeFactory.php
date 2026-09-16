<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AdCampaign;
use App\Models\AdCreative;
use App\Models\AdPlacement;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AdCreative> */
class AdCreativeFactory extends Factory
{
    protected $model = AdCreative::class;

    public function definition(): array
    {
        return [
            'ad_campaign_id' => AdCampaign::factory(),
            'ad_placement_id' => AdPlacement::factory(),
            'image_path' => '/images/ad-placeholder.png',
            'alt' => 'إعلان من جهة معلنة',
            'destination_url' => 'https://advertiser.test/landing',
            'is_active' => true,
        ];
    }
}
