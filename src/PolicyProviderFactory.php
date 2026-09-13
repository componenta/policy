<?php

declare(strict_types=1);

namespace Componenta\Policy;

use Componenta\Config\ContainerValue;
use Componenta\DI\FactoryInterface;
use Componenta\Policy\Provider\ArrayPolicyProvider;
use Componenta\Policy\Provider\AttributePolicyProvider;
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
            $providers[] = new ArrayPolicyProvider($container, $policies);
        }

        foreach ($this->providerClasses($config[ConfigKey::PROVIDERS] ?? []) as $providerClass) {
            $providers[] = $container->get($providerClass, PolicyProviderInterface::class);
        }

        $factory = $container->get(FactoryInterface::class, FactoryInterface::class);
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

}
