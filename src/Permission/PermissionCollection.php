<?php

declare(strict_types=1);

namespace Componenta\Policy\Permission;

/**
 * Default {@see PermissionCollectionInterface} - an ordered set keyed by permission name.
 *
 * The interface is read-only; this concrete class additionally exposes
 * {@see self::add()} and {@see self::remove()} for infrastructure use -
 * ORM hydration, seeders, fixtures. Consumers that type-hint against the
 * interface observe a stable set.
 */
final class PermissionCollection implements PermissionCollectionInterface
{
    /** @var array<string, PermissionInterface> */
    private array $permissions = [];

    /**
     * @param iterable<PermissionInterface> $permissions
     */
    public function __construct(iterable $permissions = [])
    {
        foreach ($permissions as $permission) {
            $this->add($permission);
        }
    }

    public function add(PermissionInterface $permission): void
    {
        $this->permissions[$permission->getName()] = $permission;
    }

    public function remove(PermissionInterface|string $permission): void
    {
        $name = $permission instanceof PermissionInterface ? $permission->getName() : $permission;

        unset($this->permissions[$name]);
    }

    public function contains(PermissionInterface|string $permission): bool
    {
        $name = $permission instanceof PermissionInterface ? $permission->getName() : $permission;

        return isset($this->permissions[$name]);
    }

    public function toArray(): array
    {
        return array_keys($this->permissions);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->permissions);
    }

    public function count(): int
    {
        return count($this->permissions);
    }
}
