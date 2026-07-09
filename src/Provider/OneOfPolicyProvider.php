<?php

declare(strict_types=1);

namespace Componenta\Policy\Provider;

use Componenta\Policy\Policies\OneOf;
use Componenta\Policy\PolicyInterface;
use Componenta\Policy\PolicyProviderInterface;

/**
 * Chains providers and combines every matching policy with OR semantics.
 *
 * Unlike {@see CompositePolicyProvider}, this provider does not stop at the
 * first match. It asks every provider for the action policy and returns:
 * - null when no provider has a policy;
 * - the single found policy as-is;
 * - {@see OneOf} when multiple policies are found.
 *
 * {@see self::add()} / {@see self::prepend()} mutate the chain and are intended
 * for bootstrap wiring only; concurrent access during request handling is not supported.
 */
final class OneOfPolicyProvider implements PolicyProviderInterface
{
    /** @var PolicyProviderInterface[] */
    private array $providers;

    /**
     * @param PolicyProviderInterface[] $providers Checked in order.
     */
    public function __construct(array $providers = [])
    {
        $this->providers = $providers;
    }

    public function provideFor(string $actionId): ?PolicyInterface
    {
        $policies = [];

        foreach ($this->providers as $provider) {
            $policy = $provider->provideFor($actionId);

            if ($policy !== null) {
                $policies[] = $policy;
            }
        }

        return match (count($policies)) {
            0 => null,
            1 => $policies[0],
            default => OneOf::of($policies),
        };
    }

    public function add(PolicyProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    public function prepend(PolicyProviderInterface $provider): void
    {
        array_unshift($this->providers, $provider);
    }
}
