<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Subscriber;
use App\Models\User;

/**
 * Reader email addresses, and a bulk action that downloads all of them as CSV.
 *
 * Held at the same bar as the settings that configure the newsletter, which is
 * the highest editorial bar we have. Before this policy existed the resource
 * had no gate of any kind: Filament allows anything it has no policy for, so
 * **every staff member who could reach the panel could list and export the
 * whole subscriber list** — measured, not inferred. A fact-checker, whose
 * entire permission set is view, transition and factcheck, could download it.
 *
 * Nobody edits a subscriber by hand. The row is written by the public signup
 * flow and changed by confirmation or unsubscribe; an editor correcting an
 * address is a support problem, not a feature.
 */
class SubscriberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('settings.manage');
    }

    public function view(User $user, Subscriber $subscriber): bool
    {
        return $user->can('settings.manage');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Subscriber $subscriber): bool
    {
        return false;
    }

    public function delete(User $user, Subscriber $subscriber): bool
    {
        return false;
    }
}
