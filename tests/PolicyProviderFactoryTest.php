<?php

declare(strict_types=1);

use Componenta\Config\Config;
use Componenta\Config\ContainerValue;
use Componenta\DI\FactoryInterface;
use Componenta\Policy\ConfigKey;
use Componenta\Policy\Context\Context;
use Componenta\Policy\Policies\Allow;
use Componenta\Policy\PolicyProviderFactory;
use Componenta\Policy\Provider\AttributePolicyProvider;
use Componenta\Policy\Tests\Fixture\FakeActor;
use Componenta\Policy\Tests\Fixture\FakeContainer;
use Componenta\Policy\Tests\Fixture\FakeFactory;
use Componenta\Policy\Tests\Fixture\FakeRole;

function policyProviderContainerValue(mixed $policyConfig = []): ContainerValue
{
    return new ContainerValue(
        new FakeContainer([
            FactoryInterface::class => new FakeFactory(),
        ]),
        new Config([ConfigKey::POLICY => $policyConfig], new \Componenta\Config\Environment([])),
    );
}

describe('PolicyProviderFactory', function () {
    it('creates the attribute provider without an app path resolver', function () {
        $provider = (new PolicyProviderFactory())(policyProviderContainerValue());

        expect($provider)->toBeInstanceOf(AttributePolicyProvider::class);
    });

    it('rejects invalid custom provider configuration eagerly', function (): void {
        expect(fn () => (new PolicyProviderFactory())(policyProviderContainerValue([
            ConfigKey::PROVIDERS => [stdClass::class],
        ])))->toThrow(InvalidArgumentException::class, 'PolicyProviderInterface');
    });

    it('validates configured policy factory results', function (): void {
        $provider = (new PolicyProviderFactory())(policyProviderContainerValue([
            ConfigKey::POLICIES => [
                'broken' => static fn () => new stdClass(),
            ],
        ]));

        expect(fn () => $provider->provideFor('broken'))
            ->toThrow(InvalidArgumentException::class, 'must return');
    });
});

it('rejects explicitly malformed policy sections instead of converting them to arrays', function (): void {
    foreach ([null, false, true, 0, 12, '', 'invalid', new stdClass()] as $value) {
        expect(fn () => (new PolicyProviderFactory())(policyProviderContainerValue($value)))
            ->toThrow(InvalidArgumentException::class, 'Policy configuration "policy" must be an array');
    }
});

it('rejects explicit null policy maps and provider lists even with permissive fallback configured', function (): void {
    foreach ([ConfigKey::POLICIES, ConfigKey::PROVIDERS] as $key) {
        expect(fn () => (new PolicyProviderFactory())(policyProviderContainerValue([
            $key => null,
            ConfigKey::MISSING_POLICY_BEHAVIOR => \Componenta\Policy\MissingPolicyBehavior::ALLOW,
        ])))->toThrow(InvalidArgumentException::class, sprintf('Policy configuration "%s" must be an array', $key));
    }
});

it('preserves defaults for an absent policy section and explicit empty lists', function (): void {
    $services = policyProviderContainerValue()->container;
    $factory = new PolicyProviderFactory();

    expect($factory(new ContainerValue($services, new Config([], new \Componenta\Config\Environment([])))))
        ->toBeInstanceOf(AttributePolicyProvider::class)
        ->and($factory(policyProviderContainerValue([
            ConfigKey::POLICIES => [],
            ConfigKey::PROVIDERS => [],
        ])))->toBeInstanceOf(AttributePolicyProvider::class);
});
