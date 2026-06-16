<?php

declare(strict_types=1);

namespace Componenta\Policy\Actor;

/**
 * Named set of permissions with a hierarchy relation for moderation scenarios.
 */
interface RoleInterface extends PermissionAwareInterface
{
    public string $name { get; }

    /**
     * Whether this role has strictly higher authority than the other.
     * Used by {@see \Componenta\Policy\Policies\HierarchyPolicy}.
     */
    public function outranks(RoleAwareInterface|RoleInterface $other): bool;
}
