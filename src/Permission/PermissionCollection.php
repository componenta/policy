<?php

declare(strict_types=1);

namespace Componenta\Policy\Permission;

use Componenta\Policy\ContainsMode;

/**
 * Default {@see PermissionCollectionInterface} implementation.
 *
 * Represents an ordered set of permissions keyed by permission name.
 * Adding a permission with an existing name replaces the previous permission.
 *
 * The interface is read-only; this concrete class additionally exposes
 * {@see self::add()} and {@see self::remove()} for infrastructure use:
 * ORM hydration, seeders, fixtures.
 *
 * Consumers that type-hint against {@see PermissionCollectionInterface}
 * observe a stable read-only permission set.
 */
final class PermissionCollection implements PermissionCollectionInterface
{
    /**
     * Permissions indexed by their unique names.
     *
     * @var array<string, PermissionInterface>
     */
    private array $permissions = [];

    /**
     * Creates a permission collection from the given iterable.
     *
     * If several permissions have the same name, the last one wins.
     *
     * @param iterable<PermissionInterface> $permissions
     */
    public function __construct(iterable $permissions = [])
    {
        foreach ($permissions as $permission) {
            $this->add($permission);
        }
    }

    /**
     * Adds a permission to the collection.
     *
     * The permission is stored by its name. If a permission with the same name
     * already exists, it will be replaced.
     */
    public function add(PermissionInterface $permission): void
    {
        $this->permissions[$permission->getName()] = $permission;
    }

    /**
     * Removes a permission from the collection.
     *
     * The permission may be passed either as a permission object or directly
     * as a permission name.
     */
    public function remove(PermissionInterface|string $permission): void
    {
        unset($this->permissions[self::permissionName($permission)]);
    }

    /**
     * Checks whether the collection contains the given permission.
     *
     * When a single permission or permission name is passed, the method checks
     * whether a permission with the same name exists in the collection.
     *
     * When another permission collection is passed, the result depends on
     * {@see ContainsMode}:
     *
     * - {@see ContainsMode::ANY}: returns true if at least one permission exists;
     * - {@see ContainsMode::ALL}: returns true only if all permissions exist.
     */
    public function contains(
        PermissionInterface|PermissionCollectionInterface|string $permission,
        ContainsMode $mode = ContainsMode::ANY,
    ): bool {
        if ($permission instanceof PermissionCollectionInterface) {
            if ($mode === ContainsMode::ANY) {
                foreach ($permission as $p) {
                    if (isset($this->permissions[$p->getName()])) {
                        return true;
                    }
                }

                return false;
            }

            foreach ($permission as $p) {
                if (!isset($this->permissions[$p->getName()])) {
                    return false;
                }
            }

            return true;
        }

        return isset($this->permissions[self::permissionName($permission)]);
    }

    /**
     * Returns all permissions as an associative array keyed by permission name.
     *
     * @return array<string, PermissionInterface>
     */
    public function toArray(): array
    {
        return $this->permissions;
    }

    /**
     * Returns an iterator over permissions.
     *
     * Iteration preserves the insertion order of permission names.
     *
     * @return \ArrayIterator<string, PermissionInterface>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->permissions);
    }

    /**
     * Returns the number of permissions in the collection.
     */
    public function count(): int
    {
        return count($this->permissions);
    }

    /**
     * Resolves a permission name from either a permission object or a string.
     */
    private static function permissionName(PermissionInterface|string $permission): string
    {
        return $permission instanceof PermissionInterface
            ? $permission->getName()
            : $permission;
    }
}