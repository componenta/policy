<?php

declare(strict_types=1);

namespace Componenta\Policy\Actor;

final class GuestActorProvider implements ActorProviderInterface
{
    public function getActor(): object
    {
        return new Guest();
    }
}