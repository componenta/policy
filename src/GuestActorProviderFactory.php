<?php

declare(strict_types=1);

namespace Componenta\Policy;

use Componenta\Policy\Actor\GuestActorProvider;

final class GuestActorProviderFactory
{
    public function __invoke(): GuestActorProvider
    {
        return new GuestActorProvider();
    }
}