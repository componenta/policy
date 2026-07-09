<?php

declare(strict_types=1);

namespace Componenta\Policy\Permission;

use Componenta\Arrayable\Arrayable;
use Componenta\Policy\ContainsMode;
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
interface PermissionCollectionInterface extends IteratorAggregate, Countable, Arrayable
{
    /**
     * Checks whether the collection contains the given permission.
     *
     * A permission may be passed as:
     *
     * - a {@see PermissionInterface} instance;
     * - a permission name string;
     * - another {@see PermissionCollectionInterface}.
     *
     * When a single permission or permission name is passed, the method checks
     * whether a permission with the same name exists in the collection.
     *
     * When another permission collection is passed, the result depends on the
     * given {@see ContainsMode}:
     *
     * - {@see ContainsMode::ANY}: returns true if at least one permission from
     *   the given collection exists in this collection;
     * - {@see ContainsMode::ALL}: returns true only if every permission from
     *   the given collection exists in this collection.
     *
     * For an empty given collection, {@see ContainsMode::ANY} returns false,
     * while {@see ContainsMode::ALL} returns true.
     */
    public function contains(
        PermissionInterface|PermissionCollectionInterface|string $permission,
        ContainsMode $mode = ContainsMode::ANY,
    ): bool;

    /**
     * Returns all permissions as an associative array keyed by permission name.
     *
     * @return array<string, PermissionInterface>
     */
    public function toArray(): array;
}