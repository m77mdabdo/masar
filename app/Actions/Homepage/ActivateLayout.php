<?php

declare(strict_types=1);

namespace App\Actions\Homepage;

use App\Models\HomepageLayout;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Makes one layout the live front page.
 *
 * Deactivating the others and activating this one happen together: two active
 * layouts is not a recoverable state, it is a coin toss about which front page
 * a reader gets.
 */
class ActivateLayout
{
    public function __invoke(HomepageLayout $layout): HomepageLayout
    {
        DB::transaction(function () use ($layout): void {
            HomepageLayout::query()
                ->whereKeyNot($layout->getKey())
                ->update(['is_active' => false]);

            $layout->forceFill(['is_active' => true])->save();
        });

        Cache::tags('homepage')->flush();

        return $layout->refresh();
    }
}
