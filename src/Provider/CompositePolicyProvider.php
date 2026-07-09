<?php

declare(strict_types=1);

namespace Componenta\Policy\Provider;

use Componenta\Policy\PolicyInterface;
use Componenta\Policy\PolicyProviderInterface;

/**
 * Chains providers and returns the first non-null match.
 *
 * {@see self::add()} / {@see self::prepend()} mutate the chain and are intended
 * for bootstrap wiring only; concurrent access during request handling is not supported.
 */
final class CompositePolicyProvider implements PolicyProviderInterface
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
        foreach ($this->providers as $provider) {
            $policy = $provider->provideFor($actionId);

            if ($policy !== null) {
                return $policy;
            }
        }

        return null;
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
