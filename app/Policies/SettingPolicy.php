<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Setting;
use App\Models\User;

/**
 * Asked by ManageSettings on mount and on save.
 *
 * Settings are edited as one form, never row by row, so there is no view,
 * create or delete to ask about — those methods existed, were never called,
 * and have been removed rather than left reading as protection. `update`
 * takes no row for the same reason.
 */
class SettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('settings.manage');
    }

    public function update(User $user, ?Setting $model = null): bool
    {
        return $user->can('settings.manage');
    }
}
