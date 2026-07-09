<?php

declare(strict_types=1);

namespace Componenta\Policy\Actor;

/**
 * Actor that exposes a collection of roles.
 *
 * Use this interface for actors that may have more than one role.
 */
interface RoleCollectionAwareInterface
{
    public RoleCollectionInterface $roles { get; }
}