<?php

declare(strict_types=1);

namespace Componenta\Policy\Actor;

/**
 * Exposes one role for policies that use role names or hierarchy relations.
 *
 * This capability is independent from identity and permission capabilities;
 * domain actors implement only the contracts their policies require.
 */
interface RoleAwareInterface
{
    public RoleInterface $role { get; }
}
