<?php

declare(strict_types=1);

namespace Componenta\Policy\Tests\Fixture;

use Componenta\Identity\Uuid;
use Componenta\Identity\UuidInterface;
use Componenta\Policy\Actor\ActorInterface;
use Componenta\Policy\Actor\RoleCollectionAwareInterface;
use Componenta\Policy\Actor\RoleCollectionInterface;
use Componenta\Policy\Actor\RoleInterface;
use Componenta\Policy\Permission\PermissionCollection;
use Componenta\Policy\Permission\PermissionCollectionInterface;

final readonly class FakeMultiRoleActor implements ActorInterface, RoleCollectionAwareInterface
{
    public PermissionCollectionInterface $permissions;
    public RoleCollectionInterface $roles;
    public UuidInterface $uuid;

    public function __construct(
        int|string|UuidInterface $uuid,
        RoleInterface ...$roles,
    ) {
        $this->uuid = $uuid instanceof UuidInterface
            ? $uuid
            : Uuid::fromString(sprintf('00000000-0000-7000-8000-%012d', (int) $uuid));
        $this->roles = new FakeRoleCollection($roles);

        $permissions = new PermissionCollection();

        foreach ($roles as $role) {
            foreach ($role->permissions as $permission) {
                $permissions->add($permission);
            }
        }

        $this->permissions = $permissions;
    }
}