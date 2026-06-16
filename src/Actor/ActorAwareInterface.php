<?php

declare(strict_types=1);

namespace Componenta\Policy\Actor;

/**
 * Marker for objects (typically commands) that carry their own actor,
 * allowing middleware to skip an explicit {@see ActorProviderInterface} lookup.
 *
 * The exposed actor is typed as {@see ActorInterface} - role-aware consumers
 * must additionally check for {@see RoleAwareInterface} when they need a role.
 */
interface ActorAwareInterface
{
    public ActorInterface $actor { get; }
}
