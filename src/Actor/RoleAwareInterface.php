<?php

declare(strict_types=1);

namespace Componenta\Policy\Actor;

/**
 * Exposes a role. Implemented by actors that are classified by a role
 * (e.g. for hierarchy comparisons or role-name allowlists). Orthogonal to
 * {@see ActorInterface}: an actor may implement both, or neither.
 */
interface RoleAwareInterface
{
    public RoleInterface $role { get; }
}
