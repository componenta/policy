<?php

declare(strict_types=1);

namespace Componenta\Policy\Actor;

use Componenta\Identity\IdentityInterface;

/**
 * Legacy convenience composite for an identity-bearing permission actor.
 *
 * PolicyEnforcer and ActorAwareInterface accept any object. New domain models
 * should implement only the capabilities they actually expose, for example
 * IdentityInterface, PermissionAwareInterface, RoleAwareInterface, or their
 * relevant combination.
 *
 * @deprecated Implement the required capability interfaces directly.
 */
interface ActorInterface extends IdentityInterface, PermissionAwareInterface
{
}
