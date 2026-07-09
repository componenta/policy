<?php

declare(strict_types=1);

namespace Componenta\Policy\Tests\Fixture;

use Componenta\Identity\Uuid;
use Componenta\Identity\UuidInterface;
use Componenta\Policy\Policies\OwnableInterface;

final readonly class FakeOwnable implements OwnableInterface
{
    public UuidInterface $ownerId;

    public function __construct(UuidInterface|string $ownerId)
    {
        $this->ownerId = $ownerId instanceof UuidInterface
            ? $ownerId
            : Uuid::fromString($ownerId);
    }
}