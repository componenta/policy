<?php

declare(strict_types=1);

namespace Componenta\Policy\Tests\Fixture;

use Componenta\Identity\Uuid;
use Componenta\Identity\UuidInterface;
use Componenta\Policy\Actor\ActorInterface;
use Componenta\Policy\Actor\RoleAwareInterface;
use Componenta\Policy\Actor\RoleInterface;
use Componenta\Policy\Permission\PermissionCollectionInterface;

final readonly class FakeActor implements ActorInterface, RoleAwareInterface
{
    public PermissionCollectionInterface $permissions;
    public UuidInterface $uuid;

    public function __construct(
        int|string|UuidInterface $uuid,
        public RoleInterface $role,
    ) {
        $this->uuid = $uuid instanceof UuidInterface
            ? $uuid
            : Uuid::fromString(sprintf('00000000-0000-7000-8000-%012d', (int) $uuid));
        $this->permissions = $role->permissions;
    }
}
