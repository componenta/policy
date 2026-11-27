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
    /** @var array<string, mixed> Registrations are validated at resolution time. */
    private readonly array $policies;

    /**
     * @param array<string, PolicyInterface|callable(ContainerInterface): PolicyInterface> $policies
     */
    public function __construct(
        private readonly ContainerInterface $container,
        array $policies = [],
    ) {
        $snapshot = [];

        foreach ($policies as $actionId => $policy) {
            // Retain policy/factory object identity, but not caller-owned array slots.
            $snapshot[$actionId] = is_array($policy)
                ? array_map(static fn (mixed $part): mixed => $part, $policy)
                : $policy;
        }

        $this->policies = $snapshot;
    }

    public function provideFor(string $actionId): ?PolicyInterface
    {
        if (!array_key_exists($actionId, $this->policies)) {
            return null;
        }

        $policy = $this->policies[$actionId];

        if ($policy instanceof PolicyInterface) {
            return $policy;
        }

        if (!is_callable($policy)) {
            throw new \InvalidArgumentException(sprintf(
                'Registered policy "%s" must implement %s or be callable; %s given.',
                $actionId,
                PolicyInterface::class,
                get_debug_type($policy),
            ));
        }

        $resolved = $policy($this->container);

        if (!$resolved instanceof PolicyInterface) {
            throw new \UnexpectedValueException(sprintf(
                'Policy factory "%s" must return %s; %s returned.',
                $actionId,
                PolicyInterface::class,
                get_debug_type($resolved),
            ));
        }

        return $resolved;
    }
}
