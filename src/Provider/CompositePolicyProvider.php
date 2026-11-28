<?php

declare(strict_types=1);

namespace Componenta\Policy\Provider;

use Componenta\Policy\PolicyInterface;
use Componenta\Policy\Provider\Internal\ProviderChain;

/**
 * Chains providers and returns the first non-null match.
 *
 * {@see self::add()} / {@see self::prepend()} mutate the chain and are intended
 * for bootstrap wiring only; concurrent access during request handling is not supported.
 */
final class CompositePolicyProvider extends ProviderChain
{
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
}
