<?php

declare(strict_types=1);

namespace Componenta\Policy\Policies;

use Componenta\Identity\UuidInterface;

/**
 * Resource with a known owner, compared by {@see OwnerPolicy} against the actor's id.
 *
 * The returned id must match the type produced by the corresponding actor's
 * Returns the public UUID identity of the owner - comparison is strict.
 */
interface OwnableInterface
{
    public UuidInterface $ownerId { get; }
}
