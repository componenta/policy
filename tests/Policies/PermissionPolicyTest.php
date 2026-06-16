<?php

declare(strict_types=1);

use Componenta\Policy\Context\Context;
use Componenta\Policy\Exception\DenyReason;
use Componenta\Policy\Exception\InvalidPolicyActorException;
use Componenta\Policy\Permission\PermissionMode;
use Componenta\Policy\Policies\PermissionPolicy;
use Componenta\Policy\Tests\Fixture\FakeActor;
use Componenta\Policy\Tests\Fixture\FakePermission;
use Componenta\Policy\Tests\Fixture\FakeRole;

beforeEach(function () {
    $this->context = new Context();
});

it('allows when the role contains the single required permission', function () {
    $actor = new FakeActor(1, new FakeRole('editor', ['posts.create']));

    expect((new PermissionPolicy(new FakePermission('posts.create')))->enforce($actor, $this->context))
        ->toBeTrue();
});

it('denies when the role lacks the required permission', function () {
    $actor = new FakeActor(1, new FakeRole('editor', ['posts.create']));

    $result = (new PermissionPolicy(new FakePermission('posts.delete')))->enforce($actor, $this->context);

    expect($result)->toBeInstanceOf(DenyReason::class)
        ->and((string) $result)->toContain('posts.delete');
});

describe('mode=All', function () {
    it('allows only when every permission is present', function () {
        $actor = new FakeActor(1, new FakeRole('editor', ['posts.update', 'posts.publish']));

        $policy = new PermissionPolicy([
            new FakePermission('posts.update'),
            new FakePermission('posts.publish'),
        ]);

        expect($policy->enforce($actor, $this->context))->toBeTrue();
    });

    it('denies and reports the missing permission in the denial message', function () {
        $actor = new FakeActor(1, new FakeRole('editor', ['posts.update']));

        $policy = new PermissionPolicy([
            new FakePermission('posts.update'),
            new FakePermission('posts.publish'),
        ]);

        $result = $policy->enforce($actor, $this->context);

        expect($result)->toBeInstanceOf(DenyReason::class)
            ->and((string) $result)->toContain('posts.publish');
    });
});

describe('mode=Any', function () {
    it('allows when at least one permission is present', function () {
        $actor = new FakeActor(1, new FakeRole('editor', ['moderator.access']));

        $policy = new PermissionPolicy(
            [new FakePermission('admin.access'), new FakePermission('moderator.access')],
            PermissionMode::ANY,
        );

        expect($policy->enforce($actor, $this->context))->toBeTrue();
    });

    it('denies when none of the permissions are present', function () {
        $actor = new FakeActor(1, new FakeRole('editor', ['other']));

        $policy = new PermissionPolicy(
            [new FakePermission('admin.access'), new FakePermission('moderator.access')],
            PermissionMode::ANY,
        );

        expect($policy->enforce($actor, $this->context))->toBeInstanceOf(DenyReason::class);
    });
});

it('throws when the actor does not expose a permission set', function () {
    expect(fn() => (new PermissionPolicy(new FakePermission('x')))->enforce(new stdClass(), $this->context))
        ->toThrow(InvalidPolicyActorException::class);
});

it('throws when the permission list is empty', function () {
    expect(fn() => new PermissionPolicy([]))->toThrow(InvalidArgumentException::class);
});

it('throws when the array contains non-PermissionInterface values', function () {
    expect(fn() => new PermissionPolicy(['string-not-allowed']))->toThrow(InvalidArgumentException::class);
});
