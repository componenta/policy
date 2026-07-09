<?php

declare(strict_types=1);

namespace Componenta\Policy\Context;

/**
 * Passthrough factory - wraps the given attributes into a {@see Context}.
 */
final readonly class ContextFactory implements ContextFactoryInterface
{
    public function create(string $actionId, array $attributes = []): ContextInterface
    {
        return new Context($attributes);
    }
}
