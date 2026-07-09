<?php

declare(strict_types=1);

namespace Componenta\Policy;

use Componenta\Policy\Context\ContextFactoryInterface;
use Psr\Container\ContainerInterface;

/**
 * DI factory: wires {@see PolicyEnforcer} from the application config.
 */
final class PolicyEnforcerFactory
{
    public function __invoke(ContainerInterface $container): PolicyEnforcer
    {
        $config = $container->get('config')[ConfigKey::POLICY] ?? [];

        return new PolicyEnforcer(
            $container->get(PolicyProviderInterface::class),
            $container->get(ContextFactoryInterface::class),
            $config[ConfigKey::MISSING_POLICY_BEHAVIOR] ?? MissingPolicyBehavior::DENY,
        );
    }
}
