<?php

declare(strict_types=1);

namespace Componenta\Policy\Policies\Internal;

use Componenta\Policy\Actor\RoleAwareInterface;
use Componenta\Policy\Actor\RoleCollectionAwareInterface;
use Componenta\Policy\Actor\RoleCollectionInterface;
use Componenta\Policy\Actor\RoleInterface;

/** @internal Resolves the role sources shared by the built-in role policies. */
final class RoleSources
{
    /** @return non-empty-list<RoleInterface|RoleCollectionInterface>|null */
    public static function extract(mixed $subject): ?array
    {
        if ($subject instanceof RoleInterface || $subject instanceof RoleCollectionInterface) {
            return [$subject];
        }

        if ($subject instanceof RoleAwareInterface) {
            return $subject instanceof RoleCollectionAwareInterface
                ? [$subject->role, $subject->roles]
                : [$subject->role];
        }

        return $subject instanceof RoleCollectionAwareInterface ? [$subject->roles] : null;
    }
}
