<?php

declare(strict_types=1);

namespace Componenta\Policy\Actor;

use Componenta\Arrayable\Arrayable;
use Componenta\Policy\ContainsMode;
use Countable;
use IteratorAggregate;

/**
 * Read-only collection of roles exposed by a
 * {@see RoleCollectionAwareInterface} actor.
 *
 * Uniqueness is expected to be enforced by role name. The interface is
 * intentionally read-only: implementations may mutate internally, for example
 * during ORM hydration, but consumers of this contract observe a stable set.
 *
 * @extends IteratorAggregate<string, RoleInterface>
 */
interface RoleCollectionInterface extends IteratorAggregate, Countable, Arrayable
{
    /**
     * Checks whether the collection contains the given role.
     *
     * A role may be passed as:
     *
     * - a {@see RoleInterface} instance;
     * - a role name string;
     * - another {@see RoleCollectionInterface}.
     *
     * When a single role or role name is passed, the method checks whether a
     * role with the same name exists in the collection.
     *
     * When another role collection is passed, the result depends on the given
     * {@see ContainsMode}:
     *
     * - {@see ContainsMode::ANY}: returns true if at least one role from the
     *   given collection exists in this collection;
     * - {@see ContainsMode::ALL}: returns true only if every role from the
     *   given collection exists in this collection.
     *
     * For an empty given collection, {@see ContainsMode::ANY} returns false,
     * while {@see ContainsMode::ALL} returns true.
     */
    public function contains(
        RoleInterface|RoleCollectionInterface|string $role,
        ContainsMode $mode = ContainsMode::ANY,
    ): bool;

    /**
     * Returns all roles as an associative array keyed by role name.
     *
     * @return array<string, RoleInterface>
     */
    public function toArray(): array;
}