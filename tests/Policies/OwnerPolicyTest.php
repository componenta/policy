<?php

declare(strict_types=1);

use Componenta\Identity\Uuid;
use Componenta\Policy\Context\Context;
use Componenta\Policy\Exception\DenyReason;
use Componenta\Policy\Exception\InvalidPolicyActorException;
use Componenta\Policy\Exception\InvalidPolicyContextAttributeException;
use Componenta\Policy\Exception\MissingPolicyContextAttributeException;
use Componenta\Policy\Policies\OwnerPolicy;
use Componenta\Policy\Tests\Fixture\FakeIdentity;
use Componenta\Policy\Tests\Fixture\FakeOwnable;

it('allows when the actor uuid matches the resource owner uuid', function () {
    $uuid = Uuid::fromString('00000000-0000-7000-8000-000000000001');
    $actor = new FakeIdentity($uuid);
    $context = new Context([OwnerPolicy::ATTR_RESOURCE => new FakeOwnable($uuid)]);

    expect((new OwnerPolicy())->enforce($actor, $context))->toBeTrue();
});

it('denies when the actor uuid does not match the resource owner uuid', function () {
    $actor = new FakeIdentity(Uuid::fromString('00000000-0000-7000-8000-000000000001'));
    $context = new Context([
        OwnerPolicy::ATTR_RESOURCE => new FakeOwnable('00000000-0000-7000-8000-000000000002'),
    ]);

    expect((new OwnerPolicy())->enforce($actor, $context))->toBeInstanceOf(DenyReason::class);
});

it('throws when the actor is not an identity', function () {
    $context = new Context([
        OwnerPolicy::ATTR_RESOURCE => new FakeOwnable('00000000-0000-7000-8000-000000000001'),
    ]);

    expect(fn() => (new OwnerPolicy())->enforce(new stdClass(), $context))
        ->toThrow(InvalidPolicyActorException::class);
});

it('throws when the context does not carry a resource', function () {
    $actor = new FakeIdentity(Uuid::fromString('00000000-0000-7000-8000-000000000001'));

    expect(fn() => (new OwnerPolicy())->enforce($actor, new Context()))
        ->toThrow(MissingPolicyContextAttributeException::class);
});

it('throws when the resource does not implement OwnableInterface', function () {
    $actor = new FakeIdentity(Uuid::fromString('00000000-0000-7000-8000-000000000001'));
    $context = new Context([OwnerPolicy::ATTR_RESOURCE => new stdClass()]);

    expect(fn() => (new OwnerPolicy())->enforce($actor, $context))
        ->toThrow(InvalidPolicyContextAttributeException::class);
});