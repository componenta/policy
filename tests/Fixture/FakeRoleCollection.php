<?php

declare(strict_types=1);

namespace Componenta\Policy\Tests\Fixture;

use ArrayIterator;
use Componenta\Policy\Actor\RoleCollectionInterface;
use Componenta\Policy\Actor\RoleInterface;
use Componenta\Policy\ContainsMode;

final class FakeRoleCollection implements RoleCollectionInterface
{
    /**
     * @var array<string, RoleInterface>
     */
    private array $roles = [];

    /**
     * @param iterable<RoleInterface> $roles
     */
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

    public function contains(
        RoleInterface|RoleCollectionInterface|string $role,
        ContainsMode $mode = ContainsMode::ANY,
    ): bool {
        if ($role instanceof RoleCollectionInterface) {
            if ($mode === ContainsMode::ANY) {
                foreach ($role as $r) {
                    if (isset($this->roles[$r->name])) {
                        return true;
                    }
                }

                return false;
            }

            foreach ($role as $r) {
                if (!isset($this->roles[$r->name])) {
                    return false;
                }
            }

            return true;
        }

        return isset($this->roles[self::roleName($role)]);
    }

    /**
     * @return array<string, RoleInterface>
     */
    public function toArray(): array
    {
        return $this->roles;
    }

    /**
     * @return ArrayIterator<string, RoleInterface>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->roles);
    }

    public function count(): int
    {
        return count($this->roles);
    }

    private static function roleName(RoleInterface|string $role): string
    {
        return $role instanceof RoleInterface ? $role->name : $role;
    }
}