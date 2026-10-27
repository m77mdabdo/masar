<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MenuItem;
use App\Models\User;

/**
 * Asked by NavigationManager on mount and again on every write, and by
 * SaveMenuTree for each item it moves.
 *
 * It was registered and asked by nothing — the page gated on the permission
 * string directly — which read as protection while protecting nothing. There
 * is no `view`: the navigation manager has no single-item view, and a method
 * no surface asks is the same dead code again.
 */
class MenuItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('navigation.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('navigation.manage');
    }

    public function update(User $user, MenuItem $model): bool
    {
        return $user->can('navigation.manage');
    }

    public function delete(User $user, MenuItem $model): bool
    {
        return $user->can('navigation.manage');
    }
}
