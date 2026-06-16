<?php

declare(strict_types=1);

namespace Componenta\Policy\Tests\Fixture;

use Componenta\DI\FactoryInterface;
use Componenta\DI\ProxyType;

/**
 * Minimal FactoryInterface implementation for tests.
 * Instantiates the requested class with constructor args from $params (named or positional).
 */
final class FakeFactory implements FactoryInterface
{
    public function make(string $entry, array $params = [], ?ProxyType $type = null): object
    {
        return new $entry(...$params);
    }
}
