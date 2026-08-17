<?php

declare(strict_types=1);

namespace Componenta\Policy;

use Componenta\Config\ContainerValue;
use Componenta\DI\FactoryInterface;
use Componenta\Policy\Provider\ArrayPolicyProvider;
use Componenta\Policy\Provider\AttributePolicyProvider;
use Componenta\Policy\Provider\CompiledPolicyProvider;
use Componenta\Policy\Provider\CompositePolicyProvider;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;

/** DI factory that assembles the application's policy providers. */
final class PolicyProviderFactory
{
    public function __invoke(ContainerValue $container): PolicyProviderInterface
    {
        $config = $container->config->array(ConfigKey::POLICY, []);

        /** @var list<PolicyProviderInterface> $providers */
        $providers = [];

        $policies = $this->configuredPolicies($config[ConfigKey::POLICIES] ?? []);
        if ($policies !== []) {
            $providers[] = new ArrayPolicyProvider($container->value, $policies);
        }

        foreach ($this->providerClasses($config[ConfigKey::PROVIDERS] ?? []) as $providerClass) {
            $providers[] = $container->get($providerClass, PolicyProviderInterface::class);
        }

        $compiledPolicies = $this->compiledPolicies($config);
        $factory = $container->get(FactoryInterface::class, FactoryInterface::class);

        if ($compiledPolicies !== []) {
            $providers[] = new CompiledPolicyProvider(
                $factory,
                $compiledPolicies,
                ($config[ConfigKey::COMPILED_POLICIES_STRICT] ?? false) === true,
            );
        }

        $providers[] = new AttributePolicyProvider($factory);

        if (count($providers) === 1) {
            return $providers[0];
        }

        return new CompositePolicyProvider($providers);
    }

    /**
     * @return array<string, PolicyInterface|callable(ContainerInterface): PolicyInterface>
     */
    private function configuredPolicies(mixed $value): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException(sprintf(
                'Policy configuration "%s" must be an array; %s given.',
                ConfigKey::POLICIES,
                get_debug_type($value),
            ));
        }

        $result = [];

        foreach ($value as $actionId => $policy) {
            if (!is_string($actionId) || $actionId === '') {
                throw new InvalidArgumentException('Configured policy action IDs must be non-empty strings.');
            }

            if ($policy instanceof PolicyInterface) {
                $result[$actionId] = $policy;
                continue;
            }

            if (!is_callable($policy)) {
                throw new InvalidArgumentException(sprintf(
                    'Configured policy "%s" must implement %s or be callable; %s given.',
                    $actionId,
                    PolicyInterface::class,
                    get_debug_type($policy),
                ));
            }

            $result[$actionId] = static function (ContainerInterface $container) use ($policy, $actionId): PolicyInterface {
                $resolved = $policy($container);

                if (!$resolved instanceof PolicyInterface) {
                    throw new InvalidArgumentException(sprintf(
                        'Configured policy factory "%s" must return %s; %s returned.',
                        $actionId,
                        PolicyInterface::class,
                        get_debug_type($resolved),
                    ));
                }

                return $resolved;
            };
        }

        return $result;
    }

    /** @return list<class-string<PolicyProviderInterface>> */
    private function providerClasses(mixed $value): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException(sprintf(
                'Policy configuration "%s" must be an array; %s given.',
                ConfigKey::PROVIDERS,
                get_debug_type($value),
            ));
        }

        $result = [];

        foreach ($value as $providerClass) {
            if (!is_string($providerClass) || !is_a($providerClass, PolicyProviderInterface::class, true)) {
                throw new InvalidArgumentException(sprintf(
                    'Configured policy provider must be a class implementing %s; %s given.',
                    PolicyProviderInterface::class,
                    is_string($providerClass) ? $providerClass : get_debug_type($providerClass),
                ));
            }

            $result[] = $providerClass;
        }

        return $result;
    }

    /**
     * @param array<array-key, mixed> $config
     * @return array<string, array<string, mixed>>
     */
    private function compiledPolicies(array $config): array
    {
        $inline = $config[ConfigKey::COMPILED_POLICIES] ?? [];

        if (is_array($inline) && $inline !== []) {
            return $this->normalizeCompiledMap($inline);
        }

        $file = $config[ConfigKey::COMPILED_POLICIES_FILE] ?? null;
        if (!is_string($file) || $file === '' || !is_file($file)) {
            return [];
        }

        $payload = require $file;

        if (!is_array($payload) || ($payload['version'] ?? null) !== ConfigKey::CACHE_VERSION) {
            return [];
        }

        return $this->normalizeCompiledMap($payload['map'] ?? []);
    }

    /** @return array<string, array<string, mixed>> */
    private function normalizeCompiledMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];

        foreach ($value as $actionId => $descriptor) {
            if (!is_string($actionId) || !is_array($descriptor)) {
                continue;
            }

            $normalized = [];
            $valid = true;

            foreach ($descriptor as $key => $item) {
                if (!is_string($key)) {
                    $valid = false;
                    break;
                }

                $normalized[$key] = $item;
            }

            if ($valid) {
                $result[$actionId] = $normalized;
            }
        }

        return $result;
    }
}
