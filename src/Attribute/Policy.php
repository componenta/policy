<?php

declare(strict_types=1);

namespace Componenta\Policy\Attribute;

use Componenta\Policy\PolicyInterface;

/**
 * Attribute reference to a policy class instantiated via DI.
 *
 * Use when a policy needs services from the container that cannot be expressed
 * as literal attribute arguments. {@see \Componenta\Policy\Provider\AttributePolicyProvider}
 * resolves the referenced class through {@see \Componenta\DI\FactoryInterface}, injecting
 * container-managed dependencies and passing {@see self::$arguments} as explicit overrides.
 *
 * Non-final so domain-specific attributes can subclass it for a cleaner call site.
 * Subclasses must also be declared `readonly`.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
readonly class Policy
{
    /**
     * @param class-string<PolicyInterface> $policy
     * @param array<string, mixed> $arguments Passed as named constructor args to the factory.
     */
    public function __construct(
        public string $policy,
        public array  $arguments = [],
    ) {}
}
