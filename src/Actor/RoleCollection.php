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
 * {@see self::add()} and {@see self::remove()} for infrastructure use such as
 * ORM hydration, seeders, and fixtures.
 */
final class RoleCollection implements RoleCollectionInterface
{
    /** @var array<string, RoleInterface> */
    private array $roles = [];

    /** @param iterable<RoleInterface> $roles */
    public function __construct(iterable $roles = [])
    {
        foreach ($roles as $role) {
            $this->add($role);
        }
    }

    public function add(RoleInterface $role): void
    {
        $this->roles[$role->name] = $role;
    }

    public function remove(RoleInterface|string $role): void
    {
        unset($this->roles[self::roleName($role)]);
    }

    public function contains(
        RoleInterface|RoleCollectionInterface|string $role,
        ContainsMode $mode = ContainsMode::ANY,
    ): bool {
        if ($role instanceof RoleCollectionInterface) {
            if ($mode === ContainsMode::ANY) {
                foreach ($role as $item) {
                    if (isset($this->roles[$item->name])) {
                        return true;
                    }
                }

                return false;
            }

            foreach ($role as $item) {
                if (!isset($this->roles[$item->name])) {
                    return false;
                }
            }

            return true;
        }

        return isset($this->roles[self::roleName($role)]);
    }

    /** @return array<string, RoleInterface> */
    public function toArray(): array
    {
        return $this->roles;
    }

    /** @return \ArrayIterator<string, RoleInterface> */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->roles);
    }

    public function count(): int
    {
        return count($this->roles);
    }

    private static function roleName(RoleInterface|string $role): string
    {
        return $role instanceof RoleInterface
            ? $role->name
            : $role;
    }
}
