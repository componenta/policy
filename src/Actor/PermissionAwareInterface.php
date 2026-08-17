<?php

declare(strict_types=1);

namespace Componenta\Policy\Actor;

use Componenta\Policy\Permission\PermissionCollectionInterface;

/**
 * Exposes a read-only collection of permissions.
 *
 * Actors may use this capability for permissions granted directly or computed
 * from domain state. {@see RoleInterface} uses the same capability for the
 * permissions attached to a role.
 */
interface PermissionAwareInterface
{
    public PermissionCollectionInterface $permissions { get; }
}
