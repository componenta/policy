<?php

namespace Componenta\Policy;

use Componenta\Policy\Actor\GuestActorProvider;

final class GuestActorProviderFactory
{
    public function __invoke(): GuestActorProvider
    {
        return new GuestActorProvider;
    }
}