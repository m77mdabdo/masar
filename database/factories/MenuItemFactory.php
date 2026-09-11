<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    protected $model = MenuItem::class;

    public function definition(): array
    {
        return [
            'menu_id' => Menu::factory(),
            'parent_id' => null,
            'label' => ['ar' => 'عنصر قائمة', 'en' => 'Menu item'],
            'url' => '/ar',
            'linkable_type' => null,
            'linkable_id' => null,
            'sort_order' => 0,
            'is_active' => true,
            'show_desktop' => true,
            'show_mobile' => true,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }

    /**
     * Point at a real entity so the URL follows the entity's slug.
     */
    public function linkedTo(object $model): static
    {
        return $this->state([
            'url' => null,
            'linkable_type' => $model->getMorphClass(),
            'linkable_id' => $model->getKey(),
        ]);
    }
}
