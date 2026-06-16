<?php

declare(strict_types=1);

namespace Componenta\Policy\Context;

/**
 * Builds a {@see ContextInterface} for a given action.
 *
 * The `actionId` argument lets implementations inject action-specific defaults
 * (e.g. auto-attach the current request resource for `posts.*` actions).
 */
interface ContextFactoryInterface
{
    /**
     * @param array<string, mixed> $attributes Initial attributes provided by the caller.
     */
    public function create(string $actionId, array $attributes = []): ContextInterface;
}
