<?php

declare(strict_types=1);

namespace Componenta\Policy\Actor;

use Componenta\Policy\Permission\PermissionCollectionInterface;

/**
 * Exposes a read-only collection of permissions.
 *
 * Implemented by {@see ActorInterface} (actor's effective permissions - typically
 * the aggregate of role permissions and any permissions granted directly to the
 * actor) and by {@see RoleInterface} (permissions attached to a role).
 */
interface PermissionAwareInterface
{
    public PermissionCollectionInterface $permissions { get; }
}
