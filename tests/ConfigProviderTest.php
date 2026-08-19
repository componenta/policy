<?php

declare(strict_types=1);

use Componenta\Config\ConfigKey as DependencyConfigKey;
use Componenta\Policy\Actor\ActorProviderInterface;
use Componenta\Policy\Actor\GuestActorProvider;
use Componenta\Policy\ConfigProvider;
use Componenta\Policy\Context\ContextFactory;
use Componenta\Policy\Context\ContextFactoryInterface;
use Componenta\Policy\PolicyEnforcer;
use Componenta\Policy\PolicyEnforcerFactory;
use Componenta\Policy\PolicyProviderFactory;
use Componenta\Policy\PolicyProviderInterface;

it('registers policy factories and stateless invokables directly', function (): void {
    $config = (new ConfigProvider())();
    $dependencies = $config[DependencyConfigKey::DEPENDENCIES];

    expect($dependencies[DependencyConfigKey::FACTORIES])->toMatchArray([
        PolicyEnforcer::class => PolicyEnforcerFactory::class,
        PolicyProviderInterface::class => PolicyProviderFactory::class,
    ])->and($dependencies[DependencyConfigKey::INVOKABLES])->toMatchArray([
        ContextFactoryInterface::class => ContextFactory::class,
        ActorProviderInterface::class => GuestActorProvider::class,
    ]);
});
