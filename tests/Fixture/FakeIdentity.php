<?php

declare(strict_types=1);

namespace Componenta\Policy\Tests\Fixture;

use Componenta\Identity\IdentityInterface;
use Componenta\Identity\UuidInterface;

final readonly class FakeIdentity implements IdentityInterface
{
    public function __construct(
        public UuidInterface $uuid,
    ) {}
}
