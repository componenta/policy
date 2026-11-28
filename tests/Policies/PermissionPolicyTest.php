<?php

declare(strict_types=1);

use Componenta\Policy\Actor\Guest;
use Componenta\Policy\Actor\PermissionAwareInterface;
use Componenta\Policy\Actor\RoleAwareInterface;
use Componenta\Policy\Actor\RoleCollection;
use Componenta\Policy\Actor\RoleCollectionAwareInterface;
use Componenta\Policy\Actor\RoleCollectionInterface;
use Componenta\Policy\Actor\RoleInterface;
use Componenta\Policy\ContainsMode;
use Componenta\Policy\Context\Context;
use Componenta\Policy\Exception\DenyReason;
use Componenta\Policy\Exception\InvalidPolicyActorException;
use Componenta\Policy\Permission\PermissionCollection;
use Componenta\Policy\Permission\PermissionCollectionInterface;
use Componenta\Policy\Permission\PermissionInterface;
use Componenta\Policy\Policies\PermissionPolicy;
use Componenta\Policy\Tests\Fixture\FakeActor;
use Componenta\Policy\Tests\Fixture\FakeMultiRoleActor;
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

    it('merges permissions from every role in a role collection', function () {
        $actor = new FakeMultiRoleActor(
            1,
            new FakeRole('writer', ['posts.update']),
            new FakeRole('publisher', ['posts.publish']),
        );

        $policy = new PermissionPolicy([
            new FakePermission('posts.update'),
            new FakePermission('posts.publish'),
        ]);

        expect($policy->enforce($actor, $this->context))->toBeTrue();
    });
});

describe('mode=Any', function () {
    it('allows when at least one permission is present', function () {
        $actor = new FakeActor(1, new FakeRole('editor', ['moderator.access']));

        $policy = new PermissionPolicy(
            [new FakePermission('admin.access'), new FakePermission('moderator.access')],
            ContainsMode::ANY,
        );

        expect($policy->enforce($actor, $this->context))->toBeTrue();
    });

    it('denies when none of the permissions are present', function () {
        $actor = new FakeActor(1, new FakeRole('editor', ['other']));

        $policy = new PermissionPolicy(
            [new FakePermission('admin.access'), new FakePermission('moderator.access')],
            ContainsMode::ANY,
        );

        expect($policy->enforce($actor, $this->context))->toBeInstanceOf(DenyReason::class);
    });
});

it('treats an empty direct role collection as a valid permission source', function (): void {
    expect((new PermissionPolicy(new FakePermission('x')))->enforce(
        new RoleCollection(),
        $this->context,
    ))->toBeInstanceOf(DenyReason::class);
});

it('treats an empty RoleCollectionAware actor as a valid permission source', function (): void {
    $actor = new readonly class implements RoleCollectionAwareInterface {
        public RoleCollectionInterface $roles;

        public function __construct()
        {
            $this->roles = new RoleCollection();
        }
    };

    expect((new PermissionPolicy(new FakePermission('x')))->enforce($actor, $this->context))
        ->toBeInstanceOf(DenyReason::class);
});

it('denies Guest as a valid anonymous actor', function () {
    expect((new PermissionPolicy(new FakePermission('x')))->enforce(new Guest(), $this->context))
        ->toBeInstanceOf(DenyReason::class);
});

it('throws when an unknown actor does not expose a permission set', function () {
    expect(fn() => (new PermissionPolicy(new FakePermission('x')))->enforce(new stdClass(), $this->context))
        ->toThrow(InvalidPolicyActorException::class);
});

it('throws when the permission list is empty', function () {
    expect(fn() => new PermissionPolicy([]))->toThrow(InvalidArgumentException::class);
});

it('throws when the array contains non-PermissionInterface values', function () {
    expect(fn() => new PermissionPolicy(['string-not-allowed']))->toThrow(InvalidArgumentException::class);
});

it('keeps required permission names independent of subsequent collection mutations', function (ContainsMode $mode): void {
    $required = new \Componenta\Policy\Permission\PermissionCollection([new FakePermission('posts.delete')]);
    $policy = new PermissionPolicy($required, $mode);
    $unauthorized = new FakeActor(1, new FakeRole('editor', ['posts.update']));
    $authorized = new FakeActor(2, new FakeRole('admin', ['posts.delete']));

    expect($policy->enforce($unauthorized, $this->context))->toBeInstanceOf(DenyReason::class)
        ->and($policy->enforce($authorized, $this->context))->toBeTrue();

    $required->remove('posts.delete');

    expect($policy->enforce($unauthorized, $this->context))->toBeInstanceOf(DenyReason::class)
        ->and($policy->enforce($authorized, $this->context))->toBeTrue();

    $required->add(new FakePermission('posts.update'));

    expect($policy->enforce($unauthorized, $this->context))->toBeInstanceOf(DenyReason::class)
        ->and($policy->enforce($authorized, $this->context))->toBeTrue();
})->with([ContainsMode::ALL, ContainsMode::ANY]);

it('captures the canonical required permission name even when the supplied object is mutable', function (): void {
    $permission = new class implements \Componenta\Policy\Permission\PermissionInterface {
        public string $name = 'posts.delete';

        public function getName(): string
        {
            return $this->name;
        }
    };
    $policy = new PermissionPolicy($permission);
    $permission->name = 'posts.update';

    expect($policy->enforce(new FakeActor(1, new FakeRole('editor', ['posts.update'])), $this->context))
        ->toBeInstanceOf(DenyReason::class)
        ->and($policy->enforce(new FakeActor(2, new FakeRole('admin', ['posts.delete'])), $this->context))
        ->toBeTrue();
});

it('continues to read the current permissions held by an actor on every check', function (): void {
    $held = new \Componenta\Policy\Permission\PermissionCollection();
    $actor = new readonly class ($held) implements \Componenta\Policy\Actor\PermissionAwareInterface {
        public function __construct(public \Componenta\Policy\Permission\PermissionCollectionInterface $permissions) {}
    };
    $policy = new PermissionPolicy(new FakePermission('posts.update'));

    expect($policy->enforce($actor, $this->context))->toBeInstanceOf(DenyReason::class);
    $held->add(new FakePermission('posts.update'));
    expect($policy->enforce($actor, $this->context))->toBeTrue();
    $held->remove('posts.update');
    expect($policy->enforce($actor, $this->context))->toBeInstanceOf(DenyReason::class);
});

final class CountingPolicyPermissions implements PermissionCollectionInterface
{
    public int $reads = 0;

    public function __construct(public PermissionCollection $inner) {}

    public function getIterator(): Traversable
    {
        ++$this->reads;
        yield from $this->inner;
    }

    public function count(): int { return count($this->inner); }
    public function toArray(): array { return $this->inner->toArray(); }

    public function contains(
        PermissionInterface|PermissionCollectionInterface|string $permission,
        ContainsMode $mode = ContainsMode::ANY,
    ): bool {
        return $this->inner->contains($permission, $mode);
    }
}

it('inspects a permission collection shared by direct and role capabilities once per check', function (): void {
    $permission = new FakePermission('posts.update');
    $held = new CountingPolicyPermissions(new PermissionCollection([$permission]));
    $actor = new class($held) implements PermissionAwareInterface, RoleAwareInterface {
        public RoleInterface $role;
        public function __construct(public PermissionCollectionInterface $permissions)
        {
            $this->role = new class($permissions) implements RoleInterface {
                public string $name = 'editor';
                public function __construct(public PermissionCollectionInterface $permissions) {}
                public function outranks(RoleAwareInterface|RoleInterface $other): bool { return false; }
            };
        }
    };
    $policy = new PermissionPolicy($permission);

    expect($policy->enforce($actor, $this->context))->toBeTrue()
        ->and($held->reads)->toBe(1);
    $held->inner->remove('posts.update');
    expect($policy->enforce($actor, $this->context))->toBeInstanceOf(DenyReason::class)
        ->and($held->reads)->toBe(2);
    $held->inner->add($permission);
    expect($policy->enforce($actor, $this->context))->toBeTrue()
        ->and($held->reads)->toBe(3);
});

it('does not enumerate a direct role collection twice if it exposes itself', function (): void {
    $actor = new class implements RoleCollectionInterface, RoleCollectionAwareInterface {
        public int $reads = 0;
        public RoleCollectionInterface $roles { get => $this; }

        public function getIterator(): Traversable
        {
            ++$this->reads;
            yield 'editor' => new FakeRole('editor', ['posts.update']);
        }

        public function count(): int { return 1; }
        public function toArray(): array { return iterator_to_array($this); }
        public function contains(
            RoleInterface|RoleCollectionInterface|string $role,
            ContainsMode $mode = ContainsMode::ANY,
        ): bool {
            return (new RoleCollection($this))->contains($role, $mode);
        }
    };

    expect((new PermissionPolicy(new FakePermission('posts.update')))->enforce($actor, $this->context))
        ->toBeTrue()
        ->and($actor->reads)->toBe(1);
});

it('matches numeric permission names without conflating distinct strings', function (): void {
    $actor = new FakeActor(1, new FakeRole('editor', ['0', '1', '01']));
    $policy = new PermissionPolicy([new FakePermission('0'), new FakePermission('1'), new FakePermission('01')]);

    expect($policy->enforce($actor, $this->context))->toBeTrue()
        ->and((new PermissionPolicy(new FakePermission('001')))->enforce($actor, $this->context))
        ->toBeInstanceOf(DenyReason::class);
});
