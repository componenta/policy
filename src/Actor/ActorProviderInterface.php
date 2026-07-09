<?php

declare(strict_types=1);

namespace Componenta\Policy\Actor;

/**
 * Resolves the current actor from authentication context.
 *
 * Returns `null` for anonymous access. The returned object is not required
 * to implement {@see ActorInterface} - built-in policies validate the type
 * themselves and return a denial when it does not fit.
 */
interface ActorProviderInterface
{
    public function getActor(): ?object;
}
