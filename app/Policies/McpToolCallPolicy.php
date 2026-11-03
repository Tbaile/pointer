<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\McpToolCall;
use App\Models\User;

class McpToolCallPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewMcpActivity);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, McpToolCall $mcpToolCall): bool
    {
        return $user->can(Permission::ViewMcpActivity);
    }
}
