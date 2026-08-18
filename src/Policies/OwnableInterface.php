<?php

declare(strict_types=1);

namespace Componenta\Policy\Policies;

use Componenta\Identity\UuidInterface;

/**
 * Resource with a known owner, compared by {@see OwnerPolicy} against the
 * actor's public UUID identity.
 *
 * Ownership is determined through {@see UuidInterface::equals()}, not object
 * identity of the UUID value itself.
 */
interface OwnableInterface
{
    public UuidInterface $ownerId { get; }
}
