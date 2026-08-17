<?php

declare(strict_types=1);

use Componenta\Policy\Actor\ActorAwareInterface;
use Componenta\Policy\Actor\Guest;

final readonly class GenericActorAwareMessage implements ActorAwareInterface
{
    public function __construct(public object $actor) {}
}

it('defines the carried policy actor as an object capability boundary', function (): void {
    $property = new ReflectionProperty(ActorAwareInterface::class, 'actor');

    expect((string) $property->getType())->toBe('object');
});

it('allows a guest to be carried explicitly by an actor-aware message', function (): void {
    $guest = new Guest();
    $message = new GenericActorAwareMessage($guest);

    expect($message->actor)->toBe($guest);
});

it('allows arbitrary policy subjects without the legacy actor composite', function (): void {
    $subject = new stdClass();
    $message = new GenericActorAwareMessage($subject);

    expect($message->actor)->toBe($subject);
});
