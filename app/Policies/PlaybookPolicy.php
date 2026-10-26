<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Playbook;
use App\Models\User;

class PlaybookPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManagePlaybooks);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Playbook $playbook): bool
    {
        return $user->can(Permission::ManagePlaybooks);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Playbook $playbook): bool
    {
        return $user->can(Permission::ManagePlaybooks);
    }
}
