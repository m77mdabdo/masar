<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MenuItem;
use App\Models\User;

class MenuItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('navigation.manage');
    }

    public function view(User $user, MenuItem $model): bool
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
