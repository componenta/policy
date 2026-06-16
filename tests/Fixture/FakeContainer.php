<?php

declare(strict_types=1);

namespace Componenta\Policy\Tests\Fixture;

use Psr\Container\ContainerInterface;

final class FakeContainer implements ContainerInterface
{
    /**
     * @param array<string, mixed> $services
     */
    public function __construct(
        private array $services = [],
    ) {}

    public function get(string $id): mixed
    {
        if (!array_key_exists($id, $this->services)) {
            throw new class ("No entry for {$id}") extends \RuntimeException implements \Psr\Container\NotFoundExceptionInterface {};
        }

        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->services);
    }
}
