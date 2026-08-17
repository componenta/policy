<?php

declare(strict_types=1);

namespace Componenta\Policy\Actor;

/**
 * Marker for messages and other objects that explicitly carry the subject on
 * whose behalf an action runs.
 *
 * Policy actors are deliberately typed as object. Each concrete policy checks
 * only the capabilities it needs, such as permissions, roles, identity, or
 * guest state. Integrations that persist an actor reference must impose their
 * own narrower requirement at that boundary.
 */
interface ActorAwareInterface
{
    public object $actor { get; }
}
