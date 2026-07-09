<?php

declare(strict_types=1);

namespace Componenta\Policy\Provider;

use Componenta\Policy\PolicyInterface;
use Componenta\Policy\PolicyProviderInterface;
use Psr\Container\ContainerInterface;

/**
 * Action-to-policy map. Each value is either a {@see PolicyInterface} instance
 * or a callable `fn(ContainerInterface): PolicyInterface`; callables are
 * resolved lazily on first access and cached per action id.
 */
final class ArrayPolicyProvider implements PolicyProviderInterface
{
    /** @var array<string, PolicyInterface> */
    private array $resolved = [];

    /**
     * @param array<string, PolicyInterface|callable(ContainerInterface): PolicyInterface> $policies
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly array $policies = [],
    ) {}

    public function provideFor(string $actionId): ?PolicyInterface
    {
        if (isset($this->resolved[$actionId])) {
            return $this->resolved[$actionId];
        }

        if (!isset($this->policies[$actionId])) {
            return null;
        }

        $policy = $this->policies[$actionId];

        if ($policy instanceof PolicyInterface) {
            return $this->resolved[$actionId] = $policy;
        }

        return $this->resolved[$actionId] = $policy($this->container);
    }
}
