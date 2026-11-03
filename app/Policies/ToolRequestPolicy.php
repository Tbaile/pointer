<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ToolRequest;
use App\Models\User;

class ToolRequestPolicy
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
    public function view(User $user, ToolRequest $toolRequest): bool
    {
        return $user->can(Permission::ManagePlaybooks);
    }
}
