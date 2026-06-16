<?php

declare(strict_types=1);

namespace Componenta\Policy\Actor;

use Componenta\Identity\IdentityInterface;

/**
 * Identity-bearing permission entity consumed by built-in permission
 * policies ({@see \Componenta\Policy\Policies\PermissionPolicy}).
 *
 * Role-aware actors additionally implement {@see RoleAwareInterface}; that
 * unlocks {@see \Componenta\Policy\Policies\RolePolicy},
 * {@see \Componenta\Policy\Policies\HierarchyPolicy} and the role-based branch of
 * {@see \Componenta\Policy\Policies\OwnerPolicy}.
 *
 * Custom policies can accept any `object` - actor typing is not enforced by
 * {@see \Componenta\Policy\PolicyInterface}.
 */
interface ActorInterface extends IdentityInterface, PermissionAwareInterface
{
}
