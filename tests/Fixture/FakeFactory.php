<?php

declare(strict_types=1);

namespace Componenta\Policy\Tests\Fixture;

use Componenta\DI\FactoryInterface;

/**
 * Minimal FactoryInterface implementation for tests.
 * Instantiates the requested class with constructor args from $params (named or positional).
 */
final class FakeFactory implements FactoryInterface
{
    public function make(string $entry, array $params = []): object
    {
        return new $entry(...$params);
    }
}
