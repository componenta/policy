<?php

declare(strict_types=1);

namespace Componenta\Policy\Permission;

use Countable;
use IteratorAggregate;

/**
 * Read-only collection of permissions exposed by a
 * {@see \Componenta\Policy\Actor\PermissionAwareInterface} (typically a role or an
 * actor).
 *
 * Uniqueness is enforced by permission name. The interface is intentionally
 * read-only - implementations are free to mutate internally (e.g. ORM
 * hydration), but consumers of this contract observe a stable set.
 *
 * @extends IteratorAggregate<string, PermissionInterface>
 */
interface PermissionCollectionInterface extends IteratorAggregate, Countable
{
    public function contains(PermissionInterface|string $permission): bool;

    /**
     * @return string[] Permission names.
     */
    public function toArray(): array;
}
