<?php

declare(strict_types=1);

use Componenta\Policy\Context\Context;
use Componenta\Policy\Exception\DenyReason;
use Componenta\Policy\Exception\InvalidPolicyActorException;
use Componenta\Policy\Policies\RolePolicy;
use Componenta\Policy\Tests\Fixture\FakeActor;
use Componenta\Policy\Tests\Fixture\FakeMultiRoleActor;
use Componenta\Policy\Tests\Fixture\FakeRole;

beforeEach(function () {
    $this->context = new Context();
});

it('allows an actor whose role name is in the single-name allowlist', function () {
    $policy = new RolePolicy('admin');
    $actor = new FakeActor(1, new FakeRole('admin'));

    expect($policy->enforce($actor, $this->context))->toBeTrue();
});

it('allows an actor whose role name is in a multi-name allowlist', function () {
    $policy = new RolePolicy(['admin', 'moderator']);
    $actor = new FakeActor(1, new FakeRole('moderator'));

    expect($policy->enforce($actor, $this->context))->toBeTrue();
});

it('allows an actor whose role collection contains an allowed role', function () {
    $policy = new RolePolicy(['admin', 'moderator']);
    $actor = new FakeMultiRoleActor(1, new FakeRole('editor'), new FakeRole('moderator'));

    expect($policy->enforce($actor, $this->context))->toBeTrue();
});

it('denies an actor whose role is not in the allowlist', function () {
    $policy = new RolePolicy(['admin', 'moderator']);
    $actor = new FakeActor(1, new FakeRole('guest'));

    $result = $policy->enforce($actor, $this->context);

    expect($result)->toBeInstanceOf(DenyReason::class)
        ->and($result->policyClass)->toBe(RolePolicy::class);
});

it('denies an actor whose role collection does not contain an allowed role', function () {
    $policy = new RolePolicy(['admin', 'moderator']);
    $actor = new FakeMultiRoleActor(1, new FakeRole('guest'), new FakeRole('editor'));

    expect($policy->enforce($actor, $this->context))->toBeInstanceOf(DenyReason::class);
});

it('throws when the actor does not expose a role', function () {
    $policy = new RolePolicy('admin');

    expect(fn() => $policy->enforce(new stdClass(), $this->context))
        ->toThrow(InvalidPolicyActorException::class);
});

it('accepts a RoleInterface directly as the actor', function () {
    $policy = new RolePolicy('admin');

    expect($policy->enforce(new FakeRole('admin'), $this->context))->toBeTrue();
});