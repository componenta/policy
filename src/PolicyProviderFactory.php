<?php

declare(strict_types=1);

namespace Componenta\Policy;

use Componenta\Config\Config;
use Componenta\DI\FactoryInterface;
use Componenta\Policy\Provider\ArrayPolicyProvider;
use Componenta\Policy\Provider\AttributePolicyProvider;
use Componenta\Policy\Provider\CompiledPolicyProvider;
use Componenta\Policy\Provider\CompositePolicyProvider;
use Psr\Container\ContainerInterface;

/**
 * DI factory: assembles the application's {@see PolicyProviderInterface}.
 *
 * Combines, in order, the configured policies map (as {@see ArrayPolicyProvider}),
 * any custom providers registered in the container, and the always-on
 * {@see AttributePolicyProvider}. Wraps multiple providers in a
 * {@see CompositePolicyProvider}; returns a single provider directly.
 */
final class PolicyProviderFactory
{
    public function __invoke(ContainerInterface $container): PolicyProviderInterface
    {
        /** @var Config $rootConfig */
        $rootConfig = $container->get('config');
        $config = $rootConfig[ConfigKey::POLICY] ?? [];

        $providers = [];

        $policies = $config[ConfigKey::POLICIES] ?? [];

        if ($policies !== []) {
            $providers[] = new ArrayPolicyProvider($container, $policies);
        }

        $providerClasses = $config[ConfigKey::PROVIDERS] ?? [];

        foreach ($providerClasses as $providerClass) {
            $providers[] = $container->get($providerClass);
        }

        $compiledPolicies = $this->compiledPolicies($config);
        $factory = $container->get(FactoryInterface::class);

        if (is_array($compiledPolicies) && $compiledPolicies !== []) {
            $providers[] = new CompiledPolicyProvider($factory, $compiledPolicies);
        }

        $providers[] = new AttributePolicyProvider(
            $factory,
        );

        if (count($providers) === 1) {
            return $providers[0];
        }

        return new CompositePolicyProvider($providers);
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, array<string, mixed>>
     */
    private function compiledPolicies(array $config): array
    {
        $inline = $config[ConfigKey::COMPILED_POLICIES] ?? [];

        if (is_array($inline) && $inline !== []) {
            return $inline;
        }

        $file = $config[ConfigKey::COMPILED_POLICIES_FILE] ?? null;

        if (!is_string($file) || $file === '') {
            return [];
        }

        if (!is_file($file)) {
            return [];
        }

        $payload = require $file;

        if (!is_array($payload) || ($payload['version'] ?? null) !== ConfigKey::CACHE_VERSION) {
            return [];
        }

        $map = $payload['map'] ?? [];

        return is_array($map) ? $map : [];
    }

}
