<?php

declare(strict_types=1);

namespace Componenta\Policy\Internal;

use Componenta\Policy\Actor\RoleInterface;
use Componenta\Policy\Exception\InvalidPermissionCollectionException;
use Componenta\Policy\Exception\InvalidRoleCollectionException;
use Componenta\Policy\Permission\PermissionInterface;

/** @internal Checks elements yielded by extension implementations of collections. */
final class CollectionElement
{
    public static function role(mixed $role): RoleInterface
    {
        if (!$role instanceof RoleInterface) {
            throw new InvalidRoleCollectionException(sprintf(
                'Role collection must yield instances of %s, got %s.',
                RoleInterface::class,
                get_debug_type($role),
            ));
        }

        return $role;
    }

    public static function permission(mixed $permission): PermissionInterface
    {
        if (!$permission instanceof PermissionInterface) {
            throw new InvalidPermissionCollectionException(sprintf(
                'Permission collection must yield instances of %s, got %s.',
                PermissionInterface::class,
                get_debug_type($permission),
            ));
        }

        return $permission;
    }
}
