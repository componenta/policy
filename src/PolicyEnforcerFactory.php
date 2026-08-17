<?php

declare(strict_types=1);

namespace Componenta\Policy;

use Componenta\Config\ContainerValue;
use Componenta\Policy\Context\ContextFactoryInterface;
use InvalidArgumentException;

/** DI factory for {@see PolicyEnforcer}. */
final class PolicyEnforcerFactory
{
    public function __invoke(ContainerValue $container): PolicyEnforcer
    {
        $config = $container->config->array(ConfigKey::POLICY, []);
        $behavior = $config[ConfigKey::MISSING_POLICY_BEHAVIOR] ?? MissingPolicyBehavior::DENY;

        if (!$behavior instanceof MissingPolicyBehavior) {
            throw new InvalidArgumentException(sprintf(
                'Policy configuration "%s" must be an instance of %s; %s given.',
                ConfigKey::MISSING_POLICY_BEHAVIOR,
                MissingPolicyBehavior::class,
                get_debug_type($behavior),
            ));
        }

        return new PolicyEnforcer(
            $container->get(PolicyProviderInterface::class, PolicyProviderInterface::class),
            $container->get(ContextFactoryInterface::class, ContextFactoryInterface::class),
            $behavior,
        );
    }
}
