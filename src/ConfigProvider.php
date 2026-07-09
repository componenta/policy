<?php

declare(strict_types=1);

namespace Componenta\Policy;

use Componenta\Policy\Actor\ActorProviderInterface;
use Componenta\Policy\Context\ContextFactory;
use Componenta\Policy\Context\ContextFactoryInterface;

/**
 * Registers policy services with the DI container.
 */
class ConfigProvider extends \Componenta\Config\ConfigProvider
{
    protected function getFactories(): array
    {
        return [
            PolicyEnforcer::class => PolicyEnforcerFactory::class,
            PolicyProviderInterface::class => PolicyProviderFactory::class,
            ActorProviderInterface::class => GuestActorProviderFactory::class,
        ];
    }

    protected function getInvokables(): array
    {
        return [
            ContextFactoryInterface::class => ContextFactory::class,
        ];
    }
}
