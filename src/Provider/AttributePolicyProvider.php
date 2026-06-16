<?php

declare(strict_types=1);

namespace Componenta\Policy\Provider;

use Componenta\DI\FactoryInterface;
use Componenta\Policy\Attribute\AllOf;
use Componenta\Policy\Attribute\OneOf;
use Componenta\Policy\Attribute\Policy;
use Componenta\Policy\Policies\AllOf as AllOfPolicy;
use Componenta\Policy\Policies\OneOf as OneOfPolicy;
use Componenta\Policy\PolicyInterface;
use Componenta\Policy\PolicyProviderInterface;

/**
 * Discovers policies from PHP attributes.
 *
 * Resolution rules by `actionId` format:
 * - `Class::method` - attributes declared on the method (PHP reflection already
 *   surfaces attributes inherited from a non-overridden parent method);
 * - `Class` - class-level attributes, collected along the whole parent chain.
 *
 * Multiple attributes on one target are combined via {@see AllOfPolicy} (AND).
 * Class-level attributes are never mixed into method-level lookups.
 *
 * Supports three attribute kinds:
 * - instances of {@see PolicyInterface} (used directly);
 * - {@see Policy} and its subclasses (resolved via {@see FactoryInterface} for DI);
 * - {@see AllOf} / {@see OneOf} composites wrapping the above.
 *
 * Results are cached per `actionId` for the lifetime of the provider.
 */
final class AttributePolicyProvider implements PolicyProviderInterface
{
    /** @var array<string, PolicyInterface|null> */
    private array $cache = [];

    public function __construct(
        private readonly FactoryInterface $factory,
    ) {}

    public function provideFor(string $actionId): ?PolicyInterface
    {
        if (array_key_exists($actionId, $this->cache)) {
            return $this->cache[$actionId];
        }

        return $this->cache[$actionId] = $this->discoverPolicy($actionId);
    }

    private function discoverPolicy(string $actionId): ?PolicyInterface
    {
        if (str_contains($actionId, '::')) {
            return $this->discoverFromMethod($actionId);
        }

        if (class_exists($actionId)) {
            return $this->discoverFromClass($actionId);
        }

        return null;
    }

    private function discoverFromMethod(string $actionId): ?PolicyInterface
    {
        [$class, $method] = explode('::', $actionId, 2);

        if (!class_exists($class)) {
            return null;
        }

        try {
            $reflection = new \ReflectionMethod($class, $method);
        } catch (\ReflectionException) {
            return null;
        }

        $policies = $this->extractPolicies($reflection);

        return $policies !== [] ? $this->buildPolicy($policies) : null;
    }

    /**
     * Walks the class and its entire parent chain, so a class-level policy
     * declared on a base class applies to every descendant.
     * Child-declared policies precede inherited ones.
     */
    private function discoverFromClass(string $class): ?PolicyInterface
    {
        $policies = [];
        $reflection = new \ReflectionClass($class);

        do {
            $policies = [...$policies, ...$this->extractPolicies($reflection)];
            $reflection = $reflection->getParentClass();
        } while ($reflection !== false);

        return $policies !== [] ? $this->buildPolicy($policies) : null;
    }

    /**
     * @return PolicyInterface[]
     */
    private function extractPolicies(\ReflectionClass|\ReflectionMethod $reflector): array
    {
        $policies = [];

        foreach ($reflector->getAttributes(PolicyInterface::class, \ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            $policies[] = $attribute->newInstance();
        }

        // Policy and its subclasses carry a class name resolved through the DI factory,
        // enabling policies with container-managed dependencies.
        foreach ($reflector->getAttributes(Policy::class, \ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            $instance = $attribute->newInstance();
            $policies[] = $this->factory->make($instance->policy, $instance->arguments);
        }

        foreach ($reflector->getAttributes(AllOf::class) as $attribute) {
            $policies[] = $this->resolveComposite($attribute->newInstance());
        }

        foreach ($reflector->getAttributes(OneOf::class) as $attribute) {
            $policies[] = $this->resolveComposite($attribute->newInstance());
        }

        return $policies;
    }

    private function resolveComposite(AllOf|OneOf $composite): PolicyInterface
    {
        $resolved = array_map(
            fn(Policy|PolicyInterface $policy): PolicyInterface =>
                $policy instanceof PolicyInterface
                    ? $policy
                    : $this->factory->make($policy->policy, $policy->arguments),
            $composite->policies,
        );

        return $composite instanceof AllOf
            ? AllOfPolicy::of($resolved)
            : OneOfPolicy::of($resolved);
    }

    /**
     * @param PolicyInterface[] $policies
     */
    private function buildPolicy(array $policies): PolicyInterface
    {
        if (count($policies) === 1) {
            return $policies[0];
        }

        return AllOfPolicy::of($policies);
    }
}
