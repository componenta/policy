<?php

declare(strict_types=1);

namespace Componenta\Policy\Tests\Fixture;

use Componenta\Policy\Permission\PermissionInterface;

final readonly class FakePermission implements PermissionInterface
{
    public function __construct(
        public string $name,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }
}
