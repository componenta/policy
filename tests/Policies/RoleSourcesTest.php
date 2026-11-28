<?php

declare(strict_types=1);

use Componenta\Policy\Actor\RoleAwareInterface;
use Componenta\Policy\Actor\RoleCollection;
use Componenta\Policy\Actor\RoleCollectionAwareInterface;
use Componenta\Policy\Actor\RoleCollectionInterface;
use Componenta\Policy\Actor\RoleInterface;
use Componenta\Policy\ContainsMode;
use Componenta\Policy\Context\Context;
use Componenta\Policy\Exception\DenyReason;
use Componenta\Policy\Permission\PermissionCollection;
use Componenta\Policy\Permission\PermissionCollectionInterface;
use Componenta\Policy\Policies\HierarchyPolicy;
use Componenta\Policy\Policies\RolePolicy;
use Componenta\Policy\Tests\Fixture\FakeRole;

class MixedRoleCollectionSubject implements RoleCollectionInterface, RoleAwareInterface, RoleCollectionAwareInterface
{
    public function __construct(
        private RoleCollectionInterface $direct,
        public RoleInterface $role,
        public RoleCollectionInterface $roles,
    ) {}

    public function getIterator(): Traversable
    {
        yield from $this->direct;
    }

    public function count(): int
    {
        return count($this->direct);
    }

    public function toArray(): array
    {
        return $this->direct->toArray();
    }

    public function contains(
        RoleInterface|RoleCollectionInterface|string $role,
        ContainsMode $mode = ContainsMode::ANY,
    ): bool {
        return $this->direct->contains($role, $mode);
    }
}

it('combines direct collection membership with both actor role capabilities', function (): void {
    $subject = new MixedRoleCollectionSubject(
        new RoleCollection([new FakeRole('user', rank: 1)]),
        new FakeRole('admin', rank: 10),
        new RoleCollection([new FakeRole('owner', rank: 20)]),
    );
    $context = new Context();

    foreach (['user', 'admin', 'owner'] as $name) {
        expect((new RolePolicy($name))->enforce($subject, $context))->toBeTrue();
    }
    expect((new RolePolicy('unknown'))->enforce($subject, $context))->toBeInstanceOf(DenyReason::class);

    $policy = new HierarchyPolicy();
    $moderator = new FakeRole('moderator', rank: 5);
    $admin = new FakeRole('admin', rank: 10);

    expect($policy->enforce($moderator, new Context([HierarchyPolicy::ATTR_TARGET => $subject])))
        ->toBeInstanceOf(DenyReason::class)
        ->and($policy->enforce($admin, new Context([HierarchyPolicy::ATTR_TARGET => $subject])))
        ->toBeInstanceOf(DenyReason::class)
        ->and($policy->enforce($subject, new Context([HierarchyPolicy::ATTR_TARGET => $moderator])))
        ->toBeTrue();

    $subject->role = new FakeRole('editor', rank: 2);
    $subject->roles = new RoleCollection();
    expect($policy->enforce($moderator, new Context([HierarchyPolicy::ATTR_TARGET => $subject])))
        ->toBeTrue()
        ->and((new RolePolicy('owner'))->enforce($subject, $context))
        ->toBeInstanceOf(DenyReason::class);
});

it('considers the collection exposed by a direct role subject', function (): void {
    $subject = new class(new RoleCollection([new FakeRole('admin', rank: 10)]))
        implements RoleInterface, RoleCollectionAwareInterface {
        public string $name = 'user';
        public PermissionCollectionInterface $permissions;

        public function __construct(public RoleCollectionInterface $roles)
        {
            $this->permissions = new PermissionCollection();
        }

        public function outranks(RoleAwareInterface|RoleInterface $other): bool
        {
            return false;
        }
    };
    $moderator = new FakeRole('moderator', rank: 5);
    $policy = new HierarchyPolicy();
    $context = new Context();

    expect((new RolePolicy('user'))->enforce($subject, $context))->toBeTrue()
        ->and((new RolePolicy('admin'))->enforce($subject, $context))->toBeTrue()
        ->and($policy->enforce($subject, new Context([HierarchyPolicy::ATTR_TARGET => $moderator])))->toBeTrue()
        ->and($policy->enforce($moderator, new Context([HierarchyPolicy::ATTR_TARGET => $subject])))
        ->toBeInstanceOf(DenyReason::class);
});

it('distinguishes both direct role contracts instead of choosing a single interpretation', function (): void {
    $subject = new class(
        new RoleCollection([new FakeRole('owner', rank: 20)]),
        new FakeRole('user', rank: 1),
        new RoleCollection(),
    ) extends MixedRoleCollectionSubject implements RoleInterface {
        public string $name = 'direct';
        public PermissionCollectionInterface $permissions {
            get => $this->role->permissions;
        }

        public function outranks(RoleAwareInterface|RoleInterface $other): bool
        {
            return $this->role->outranks($other);
        }
    };

    foreach (['direct', 'user', 'owner'] as $name) {
        expect((new RolePolicy($name))->enforce($subject, new Context()))->toBeTrue();
    }

    expect((new HierarchyPolicy())->enforce(
        new FakeRole('admin', rank: 10),
        new Context([HierarchyPolicy::ATTR_TARGET => $subject]),
    ))->toBeInstanceOf(DenyReason::class);
});

it('does not enumerate the same directly exposed collection twice', function (): void {
    $subject = new class implements RoleCollectionInterface, RoleCollectionAwareInterface {
        public int $reads = 0;
        public RoleCollectionInterface $roles {
            get => $this;
        }

        public function getIterator(): Traversable
        {
            ++$this->reads;
            yield 'user' => new FakeRole('user', rank: 1);
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

    expect((new HierarchyPolicy())->enforce(
        new FakeRole('admin', rank: 10),
        new Context([HierarchyPolicy::ATTR_TARGET => $subject]),
    ))->toBeTrue()
        ->and($subject->reads)->toBe(1);
});
