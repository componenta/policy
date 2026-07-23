<?php

declare(strict_types=1);

namespace Componenta\Policy\Actor;

use Componenta\Policy\ContainsMode;

/**
 * Default {@see RoleCollectionInterface} implementation.
 *
 * Represents an ordered set of roles keyed by role name.
 * Adding a role with an existing name replaces the previous role.
 *
 * The interface is read-only; this concrete class additionally exposes
 * {@see self::add()} and {@see self::remove()} for infrastructure use:
 * ORM hydration, seeders, fixtures.
 *
 * Consumers that type-hint against {@see RoleCollectionInterface}
 * observe a stable read-only role set.
 */
final class RoleCollection implements RoleCollectionInterface
{
    /**
     * Roles indexed by their unique names.
     *
     * @var array<string, RoleInterface>
     */
    private array $roles = [];

    /**
     * Creates a role collection from the given iterable.
     *
     * If several roles have the same name, the last one wins.
     *
     * @param iterable<RoleInterface> $roles
     */
    public function __construct(iterable $roles = [])
    {
        foreach ($roles as $role) {
            $this->add($role);
        }
    }

    /**
     * Adds a role to the collection.
     *
     * The role is stored by its name. If a role with the same name already
     * exists, it will be replaced.
     */
    public function add(RoleInterface $role): void
    {
        $this->roles[$role->getName()] = $role;
    }

    /**
     * Removes a role from the collection.
     *
     * The role may be passed either as a role object or directly as a role
     * name.
     */
    public function remove(RoleInterface|string $role): void
    {
        unset($this->roles[self::roleName($role)]);
    }

    /**
     * Checks whether the collection contains the given role.
     *
     * When a single role or role name is passed, the method checks whether a
     * role with the same name exists in the collection.
     *
     * When another role collection is passed, the result depends on
     * {@see ContainsMode}:
     *
     * - {@see ContainsMode::ANY}: returns true if at least one role exists;
     * - {@see ContainsMode::ALL}: returns true only if all roles exist.
     *
     * For an empty given collection, {@see ContainsMode::ANY} returns false,
     * while {@see ContainsMode::ALL} returns true.
     */
    public function contains(
        RoleInterface|RoleCollectionInterface|string $role,
        ContainsMode $mode = ContainsMode::ANY,
    ): bool {
        if ($role instanceof RoleCollectionInterface) {
            if ($mode === ContainsMode::ANY) {
                foreach ($role as $item) {
                    if (isset($this->roles[$item->getName()])) {
                        return true;
                    }
                }

                return false;
            }

            foreach ($role as $item) {
                if (!isset($this->roles[$item->getName()])) {
                    return false;
                }
            }

            return true;
        }

        return isset($this->roles[self::roleName($role)]);
    }

    /**
     * Returns all roles as an associative array keyed by role name.
     *
     * @return array<string, RoleInterface>
     */
    public function toArray(): array
    {
        return $this->roles;
    }

    /**
     * Returns an iterator over roles.
     *
     * Iteration preserves the insertion order of role names.
     *
     * @return \ArrayIterator<string, RoleInterface>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->roles);
    }

    /**
     * Returns the number of roles in the collection.
     */
    public function count(): int
    {
        return count($this->roles);
    }

    /**
     * Resolves a role name from either a role object or a string.
     */
    private static function roleName(RoleInterface|string $role): string
    {
        return $role instanceof RoleInterface
            ? $role->getName()
            : $role;
    }
}
