<?php

namespace App\Enums;

/**
 * A group of permissions a user can hold. Code checks permissions, never roles,
 * except for the super-admin, which bypasses every check.
 */
enum Role: string
{
    case SuperAdmin = 'super-admin';

    case Admin = 'admin';

    case Contributor = 'contributor';

    case Technician = 'technician';
}
