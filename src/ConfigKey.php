<?php

declare(strict_types=1);

namespace Componenta\Policy;

/**
 * Configuration keys consumed by {@see PolicyEnforcerFactory} and {@see PolicyProviderFactory}.
 */
final class ConfigKey
{
    public const string POLICY = 'policy';

    /**
     * Action-to-policy factory map for {@see \Componenta\Policy\Provider\ArrayPolicyProvider}.
     *
     * Shape: array<string, PolicyInterface|callable(\Psr\Container\ContainerInterface): PolicyInterface>.
     * Each callable is resolved lazily with the container.
     */
    public const string POLICIES = 'policies';

    /**
     * Additional provider class names, each resolved through the container.
     *
     * Shape: list<class-string<PolicyProviderInterface>>.
     */
    public const string PROVIDERS = 'providers';

    /**
     * Fallback for missing policies. Defaults to {@see MissingPolicyBehavior::DENY}.
     */
    public const string MISSING_POLICY_BEHAVIOR = 'missing_policy_behavior';
}
