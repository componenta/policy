<?php

declare(strict_types=1);

namespace Componenta\Policy\Provider;

use Componenta\DI\FactoryInterface;
use Componenta\Policy\Policies\AllOf;
use Componenta\Policy\Policies\OneOf;
use Componenta\Policy\PolicyInterface;
use Componenta\Policy\PolicyProviderInterface;

/**
 * Policy provider backed by discovery-compiled descriptors.
 *
 * The provider intentionally returns null for malformed/stale descriptors so
 * CompositePolicyProvider can continue to the AttributePolicyProvider fallback.
 */
final class CompiledPolicyProvider implements PolicyProviderInterface
{
    /** @var array<string, PolicyInterface> */
    private array $resolved = [];

    /**
     * @param array<string, array<string, mixed>> $policies
     */
    public function __construct(
        private readonly FactoryInterface $factory,
        private readonly array $policies,
    ) {}

    public function provideFor(string $actionId): ?PolicyInterface
    {
        if (isset($this->resolved[$actionId])) {
            return $this->resolved[$actionId];
        }

        $descriptor = $this->policies[$actionId] ?? null;

        if (!is_array($descriptor)) {
            return null;
        }

        $policy = $this->build($descriptor);

        if ($policy === null) {
            return null;
        }

        return $this->resolved[$actionId] = $policy;
    }

    /**
     * @param array<string, mixed> $descriptor
     */
    private function build(array $descriptor): ?PolicyInterface
    {
        $kind = $descriptor['kind'] ?? null;

        try {
            return match ($kind) {
                'direct' => $this->buildDirect($descriptor),
                'factory' => $this->buildFactory($descriptor),
                'all_of' => $this->buildComposite($descriptor, all: true),
                'one_of' => $this->buildComposite($descriptor, all: false),
                default => null,
            };
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $descriptor
     */
    private function buildDirect(array $descriptor): ?PolicyInterface
    {
        $class = $descriptor['class'] ?? null;
        $arguments = $descriptor['arguments'] ?? [];

        if (!is_string($class) || !is_array($arguments) || !is_a($class, PolicyInterface::class, true)) {
            return null;
        }

        return new $class(...$arguments);
    }

    /**
     * @param array<string, mixed> $descriptor
     */
    private function buildFactory(array $descriptor): ?PolicyInterface
    {
        $policy = $descriptor['policy'] ?? null;
        $arguments = $descriptor['arguments'] ?? [];

        if (!is_string($policy) || !is_array($arguments) || !is_a($policy, PolicyInterface::class, true)) {
            return null;
        }

        $resolved = $this->factory->make($policy, $arguments);

        return $resolved instanceof PolicyInterface ? $resolved : null;
    }

    /**
     * @param array<string, mixed> $descriptor
     */
    private function buildComposite(array $descriptor, bool $all): ?PolicyInterface
    {
        $children = $descriptor['policies'] ?? null;

        if (!is_array($children)) {
            return null;
        }

        $policies = [];
        foreach ($children as $child) {
            if (!is_array($child)) {
                return null;
            }

            $policy = $this->build($child);
            if ($policy === null) {
                return null;
            }

            $policies[] = $policy;
        }

        return $all ? AllOf::of($policies) : OneOf::of($policies);
    }
}
