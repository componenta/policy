<?php

declare(strict_types=1);

use Componenta\Policy\Actor\Guest;
use Componenta\Policy\Context\Context;
use Componenta\Policy\Exception\DenyReason;
use Componenta\Policy\Exception\InvalidPolicyActorException;
use Componenta\Policy\Exception\InvalidPolicyContextAttributeException;
use Componenta\Policy\Exception\MissingPolicyContextAttributeException;
use Componenta\Policy\Policies\HierarchyPolicy;
use Componenta\Policy\Tests\Fixture\FakeActor;
use Componenta\Policy\Tests\Fixture\FakeMultiRoleActor;
use Componenta\Policy\Tests\Fixture\FakeRole;

it('allows when the actor role outranks the target role', function () {
    $actor = new FakeActor(1, new FakeRole('admin', rank: 10));
    $target = new FakeActor(2, new FakeRole('user', rank: 1));

    expect((new HierarchyPolicy())->enforce(
        $actor,
        new Context([HierarchyPolicy::ATTR_TARGET => $target]),
    ))->toBeTrue();
});

it('allows when one actor role outranks every target role', function () {
    $actor = new FakeMultiRoleActor(
        1,
        new FakeRole('editor', rank: 2),
        new FakeRole('admin', rank: 10),
    );
    $target = new FakeMultiRoleActor(
        2,
        new FakeRole('user', rank: 1),
        new FakeRole('moderator', rank: 5),
    );

    expect((new HierarchyPolicy())->enforce(
        $actor,
        new Context([HierarchyPolicy::ATTR_TARGET => $target]),
    ))->toBeTrue();
});

it('denies when the actor role does not outrank the target role', function () {
    $actor = new FakeActor(1, new FakeRole('user', rank: 1));
    $target = new FakeActor(2, new FakeRole('admin', rank: 10));

    expect((new HierarchyPolicy())->enforce(
        $actor,
        new Context([HierarchyPolicy::ATTR_TARGET => $target]),
    ))->toBeInstanceOf(DenyReason::class);
});

it('denies when actor roles outrank only part of the target role collection', function () {
    $actor = new FakeMultiRoleActor(1, new FakeRole('moderator', rank: 5));
    $target = new FakeMultiRoleActor(
        2,
        new FakeRole('user', rank: 1),
        new FakeRole('admin', rank: 10),
    );

    expect((new HierarchyPolicy())->enforce(
        $actor,
        new Context([HierarchyPolicy::ATTR_TARGET => $target]),
    ))->toBeInstanceOf(DenyReason::class);
});

it('accepts role instances directly as actor and target', function () {
    expect((new HierarchyPolicy())->enforce(
        new FakeRole('admin', rank: 10),
        new Context([HierarchyPolicy::ATTR_TARGET => new FakeRole('user', rank: 1)]),
    ))->toBeTrue();
});

it('denies Guest as a valid anonymous actor', function () {
    $target = new FakeActor(2, new FakeRole('user'));

    expect((new HierarchyPolicy())->enforce(
        new Guest(),
        new Context([HierarchyPolicy::ATTR_TARGET => $target]),
    ))->toBeInstanceOf(DenyReason::class);
});

it('throws when an unknown actor has no role', function () {
    $target = new FakeActor(2, new FakeRole('user'));

    expect(fn() => (new HierarchyPolicy())->enforce(
        new stdClass(),
        new Context([HierarchyPolicy::ATTR_TARGET => $target]),
    ))->toThrow(InvalidPolicyActorException::class);
});

it('throws when the target is missing from the context', function () {
    $actor = new FakeActor(1, new FakeRole('admin', rank: 10));

    expect(fn() => (new HierarchyPolicy())->enforce($actor, new Context()))
        ->toThrow(MissingPolicyContextAttributeException::class);
});

it('does not let Guest hide a missing target context', function () {
    expect(fn() => (new HierarchyPolicy())->enforce(new Guest(), new Context()))
        ->toThrow(MissingPolicyContextAttributeException::class);
});

it('throws when the target is not role-aware', function () {
    $actor = new FakeActor(1, new FakeRole('admin', rank: 10));

    expect(fn() => (new HierarchyPolicy())->enforce(
        $actor,
        new Context([HierarchyPolicy::ATTR_TARGET => new stdClass()]),
    ))->toThrow(InvalidPolicyContextAttributeException::class);
});

it('denies when ranks are equal (strict superiority required)', function () {
    $actor = new FakeActor(1, new FakeRole('moderator', rank: 5));
    $target = new FakeActor(2, new FakeRole('moderator', rank: 5));

    expect((new HierarchyPolicy())->enforce(
        $actor,
        new Context([HierarchyPolicy::ATTR_TARGET => $target]),
    ))->toBeInstanceOf(DenyReason::class);
});
