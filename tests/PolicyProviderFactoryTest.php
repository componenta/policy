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

/** @param array<string, mixed> $policyConfig */
function policyProviderContainerValue(array $policyConfig = []): ContainerValue
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
