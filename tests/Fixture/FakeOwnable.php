<?php

declare(strict_types=1);

namespace Componenta\Policy\Tests\Fixture;

use Componenta\Identity\UuidInterface;
use Componenta\Policy\Policies\OwnableInterface;

final readonly class FakeOwnable implements OwnableInterface
{
    public function __construct(
        private UuidInterface|string $ownerUuid,
    ) {}

    public function getOwnerUuid(): UuidInterface|string
    {
        return $this->ownerUuid;
    }
}
