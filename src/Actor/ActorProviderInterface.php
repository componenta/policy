<?php

declare(strict_types=1);

namespace Componenta\Policy\Actor;

/**
 * Resolves the current policy actor from an integration-specific context.
 *
 * Implementations return the resolved actor object, {@see Guest} when they
 * explicitly model anonymous access, or null when no actor can be resolved.
 * The policy layer does not implicitly convert null to Guest.
 */
interface ActorProviderInterface
{
    public function getActor(): ?object;
}
