<?php

namespace Componenta\Policy\Actor;

final class GuestActorProvider implements ActorProviderInterface
{
    public function getActor(): ?object
    {
        return new Guest;
    }
}