<?php

declare(strict_types=1);

use Componenta\Policy\Context\Context;
use Componenta\Policy\Exception\DenyReason;
use Componenta\Policy\Exception\InvalidPolicyActorException;
use Componenta\Policy\Exception\InvalidPolicyContextAttributeException;
use Componenta\Policy\Exception\MissingPolicyContextAttributeException;
use Componenta\Policy\Policies\HierarchyPolicy;
use Componenta\Policy\Tests\Fixture\FakeActor;
use Componenta\Policy\Tests\Fixture\FakeRole;

it('allows when the actor role outranks the target role', function () {
    $actor = new FakeActor(1, new FakeRole('admin', rank: 10));
    $target = new FakeActor(2, new FakeRole('user', rank: 1));

    $context = new Context([HierarchyPolicy::ATTR_TARGET => $target]);

    expect((new HierarchyPolicy())->enforce($actor, $context))->toBeTrue();
});

it('denies when the actor role does not outrank the target role', function () {
    $actor = new FakeActor(1, new FakeRole('user', rank: 1));
    $target = new FakeActor(2, new FakeRole('admin', rank: 10));

    $context = new Context([HierarchyPolicy::ATTR_TARGET => $target]);

    expect((new HierarchyPolicy())->enforce($actor, $context))->toBeInstanceOf(DenyReason::class);
});

it('throws when the actor has no role', function () {
    $target = new FakeActor(2, new FakeRole('user'));
    $context = new Context([HierarchyPolicy::ATTR_TARGET => $target]);

    expect(fn() => (new HierarchyPolicy())->enforce(new stdClass(), $context))
        ->toThrow(InvalidPolicyActorException::class);
});

it('throws when the target is missing from the context', function () {
    $actor = new FakeActor(1, new FakeRole('admin', rank: 10));

    expect(fn() => (new HierarchyPolicy())->enforce($actor, new Context()))
        ->toThrow(MissingPolicyContextAttributeException::class);
});

it('throws when the target is not role-aware', function () {
    $actor = new FakeActor(1, new FakeRole('admin', rank: 10));
    $context = new Context([HierarchyPolicy::ATTR_TARGET => new stdClass()]);

    expect(fn() => (new HierarchyPolicy())->enforce($actor, $context))
        ->toThrow(InvalidPolicyContextAttributeException::class);
});

it('denies when ranks are equal (strict superiority required)', function () {
    $actor = new FakeActor(1, new FakeRole('moderator', rank: 5));
    $target = new FakeActor(2, new FakeRole('moderator', rank: 5));

    $context = new Context([HierarchyPolicy::ATTR_TARGET => $target]);

    expect((new HierarchyPolicy())->enforce($actor, $context))->toBeInstanceOf(DenyReason::class);
});
