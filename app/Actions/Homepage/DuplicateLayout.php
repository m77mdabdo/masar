<?php

declare(strict_types=1);

namespace App\Actions\Homepage;

use App\Models\HomepageLayout;
use Illuminate\Support\Facades\DB;

/**
 * Copies a layout and its sections.
 *
 * This is how a seasonal or scheduled front page starts: take the one that
 * works, change three things. The copy is always inactive — duplicating must
 * never change what readers see.
 */
class DuplicateLayout
{
    public function __invoke(HomepageLayout $layout, ?string $name = null): HomepageLayout
    {
        return DB::transaction(function () use ($layout, $name): HomepageLayout {
            $copy = HomepageLayout::query()->create([
                'name' => $name ?? $layout->name.' (نسخة)',
                'is_active' => false,
                'starts_at' => null,
                'ends_at' => null,
            ]);

            foreach ($layout->sections()->orderBy('sort_order')->get() as $section) {
                $copy->sections()->create([
                    'type' => $section->type,
                    'title' => $section->title,
                    'source' => $section->source,
                    'config' => $section->config,
                    'sort_order' => $section->sort_order,
                    'is_visible' => $section->is_visible,
                ]);
            }

            return $copy;
        });
    }
}
