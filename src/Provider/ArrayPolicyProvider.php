<?php

declare(strict_types=1);

namespace Componenta\Policy\Provider;

use Componenta\Policy\PolicyInterface;
use Componenta\Policy\PolicyProviderInterface;
use Psr\Container\ContainerInterface;

/**
 * Action-to-policy map. Each value is either a {@see PolicyInterface} instance
 * or a callable `fn(ContainerInterface): PolicyInterface` evaluated on each
 * resolution. Object registrations retain their explicitly selected lifetime.
 */
final class ArrayPolicyProvider implements PolicyProviderInterface
{
    /**
     * @param array<string, PolicyInterface|callable(ContainerInterface): PolicyInterface> $policies
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly array $policies = [],
    ) {}

    public function provideFor(string $actionId): ?PolicyInterface
    {
        if (!isset($this->policies[$actionId])) {
            return null;
        }

        $policy = $this->policies[$actionId];

        if ($policy instanceof PolicyInterface) {
            return $policy;
        }

        return $policy($this->container);
    }
}
