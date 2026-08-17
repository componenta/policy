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
    $provider = new class implements PolicyProviderInterface {
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
        ]),
    );
}

it('builds the enforcer from typed ContainerValue services and policy config', function (): void {
    $enforcer = (new PolicyEnforcerFactory())(
        policyEnforcerContainerValue(MissingPolicyBehavior::ALLOW),
    );

    expect($enforcer->can('missing.action', new Guest()))->toBeTrue();
});

it('rejects an invalid missing-policy behavior configuration', function (): void {
    expect(fn() => (new PolicyEnforcerFactory())(
        policyEnforcerContainerValue('allow'),
    ))->toThrow(InvalidArgumentException::class, MissingPolicyBehavior::class);
});
