<?php

declare(strict_types=1);

use Componenta\Config\Config;
use Componenta\Config\ContainerValue;
use Componenta\Policy\Actor\Guest;
use Componenta\Policy\ConfigKey;
use Componenta\Policy\Context\ContextFactory;
use Componenta\Policy\Context\ContextFactoryInterface;
use Componenta\Policy\MissingPolicyBehavior;
use Componenta\Policy\PolicyEnforcerFactory;
use Componenta\Policy\PolicyProviderInterface;
use Componenta\Policy\Tests\Fixture\FakeContainer;

function policyEnforcerContainerValue(mixed $behavior = MissingPolicyBehavior::DENY): ContainerValue
{
    $provider = new class () implements PolicyProviderInterface {
        public function provideFor(string $actionId): ?Componenta\Policy\PolicyInterface
        {
            return null;
        }
    };

    return new ContainerValue(
        new FakeContainer([
            PolicyProviderInterface::class => $provider,
            ContextFactoryInterface::class => new ContextFactory(),
        ]),
        new Config([
            ConfigKey::POLICY => [
                ConfigKey::MISSING_POLICY_BEHAVIOR => $behavior,
            ],
        ], new \Componenta\Config\Environment([])),
    );
}

it('builds the enforcer from typed ContainerValue services and policy config', function (): void {
    $enforcer = (new PolicyEnforcerFactory())(
        policyEnforcerContainerValue(MissingPolicyBehavior::ALLOW),
    );

    expect($enforcer->can('missing.action', new Guest()))->toBeTrue();
});

it('rejects an invalid missing-policy behavior configuration', function (): void {
    expect(fn () => (new PolicyEnforcerFactory())(
        policyEnforcerContainerValue('allow'),
    ))->toThrow(InvalidArgumentException::class, MissingPolicyBehavior::class);
});

it('rejects explicitly malformed root sections instead of silently using default behavior', function (): void {
    $services = policyEnforcerContainerValue()->container;
    $factory = new PolicyEnforcerFactory();

    foreach ([null, false, true, 0, 12, '', 'invalid', new stdClass()] as $value) {
        $container = new ContainerValue($services, new Config([
            ConfigKey::POLICY => $value,
        ], new \Componenta\Config\Environment([])));

        expect(fn () => $factory($container))
            ->toThrow(InvalidArgumentException::class, 'Policy configuration "policy" must be an array');
    }
});

it('rejects an explicit null fallback and reports the expected enum for invalid values', function (): void {
    foreach ([null, false, 42, 'allow', []] as $value) {
        expect(fn () => (new PolicyEnforcerFactory())(policyEnforcerContainerValue($value)))
            ->toThrow(InvalidArgumentException::class, sprintf(
                'Policy configuration "%s" must be an instance of %s; %s given.',
                ConfigKey::MISSING_POLICY_BEHAVIOR,
                MissingPolicyBehavior::class,
                get_debug_type($value),
            ));
    }
});

it('preserves deny by default when the section or fallback key is absent', function (): void {
    $services = policyEnforcerContainerValue()->container;
    $factory = new PolicyEnforcerFactory();
    foreach ([[], [ConfigKey::POLICY => []]] as $config) {
        $container = new ContainerValue($services, new Config($config, new \Componenta\Config\Environment([])));

        expect($factory($container)->can('missing.action', new Guest()))->toBeFalse();
    }
});
