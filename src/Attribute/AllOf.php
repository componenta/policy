<?php

declare(strict_types=1);

namespace Componenta\Policy\Attribute;

use Componenta\Policy\PolicyInterface;

/**
 * Declarative AND-composition of policies on a class or method.
 *
 * Accepts both {@see Policy} attribute references (resolved via DI) and
 * {@see PolicyInterface} instances (used as-is).
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
final readonly class AllOf
{
    /** @var array<Policy|PolicyInterface> */
    public array $policies;

    public function __construct(Policy|PolicyInterface ...$policies)
    {
        $this->policies = $policies;
    }
}
