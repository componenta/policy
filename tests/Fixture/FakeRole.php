<?php

declare(strict_types=1);

namespace Componenta\Policy\Tests\Fixture;

use Componenta\Policy\Actor\RoleAwareInterface;
use Componenta\Policy\Actor\RoleInterface;
use Componenta\Policy\Permission\PermissionCollection;
use Componenta\Policy\Permission\PermissionCollectionInterface;

final readonly class FakeRole implements RoleInterface
{
    public PermissionCollectionInterface $permissions;

    /**
     * @param string[] $permissions
     */
    public function __construct(
        public string $name,
        array $permissions = [],
        private int $rank = 0,
    ) {
        $this->permissions = new PermissionCollection(
            array_map(static fn(string $n): FakePermission => new FakePermission($n), $permissions),
        );
    }

    public function outranks(RoleAwareInterface|RoleInterface $other): bool
    {
        $otherRole = $other instanceof RoleAwareInterface ? $other->role : $other;
        $otherRank = $otherRole instanceof self ? $otherRole->rank : 0;

        return $this->rank > $otherRank;
    }
}
