<?php

namespace App\Enums;

/**
 * An action a role grants, checked with `$user->can()`.
 */
enum Permission: string
{
    case UseMcp = 'use mcp';

    case ManagePlaybooks = 'manage playbooks';

    case ManageUsers = 'manage users';

    case ViewMcpActivity = 'view mcp activity';

    // Attached to no role, only the super-admin gets it
    case AssignAdminRoles = 'assign admin roles';

    // Attached to no role, only the super-admin gets it
    case ManagePointer = 'manage pointer';
}
