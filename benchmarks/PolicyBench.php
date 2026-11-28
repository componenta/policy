<?php

declare(strict_types=1);

namespace Componenta\Policy\Benchmarks;

use Componenta\Policy\ContainsMode;
use Componenta\Policy\Context\Context;
use Componenta\Policy\Context\ContextInterface;
use Componenta\Policy\Exception\DenyReason;
use Componenta\Policy\Policies\AllOf;
use Componenta\Policy\Policies\CallbackPolicy;
use Componenta\Policy\Policies\HierarchyPolicy;
use Componenta\Policy\Policies\OneOf;
use Componenta\Policy\Policies\OwnerPolicy;
use Componenta\Policy\Policies\PermissionPolicy;
use Componenta\Policy\Policies\RolePolicy;
use Componenta\Policy\Tests\Fixture\FakeActor;
use Componenta\Policy\Tests\Fixture\FakeMultiRoleActor;
use Componenta\Policy\Tests\Fixture\FakeOwnable;
use Componenta\Policy\Tests\Fixture\FakePermission;
use Componenta\Policy\Tests\Fixture\FakeRole;
use PhpBench\Attributes as Bench;

#[Bench\BeforeMethods(['setUp'])]
#[Bench\Revs(1000)]
#[Bench\Iterations(5)]
final class PolicyBench
{
    private Context $context;
    private Context $ownerContext;
    private Context $hierarchyContext;
    private FakeActor $actor;
    private FakeMultiRoleActor $multiActor;
    private FakeActor $largeActor;
    private RolePolicy $role;
    private RolePolicy $deniedRole;
    private PermissionPolicy $allPermissions;
    private PermissionPolicy $anyPermission;
    private PermissionPolicy $missingPermission;
    private PermissionPolicy $largePermission;
    private HierarchyPolicy $hierarchy;
    private OwnerPolicy $owner;
    private CallbackPolicy $callback;
    private AllOf $all;
    private OneOf $one;

    public function setUp(): void
    {
        $this->context = new Context();
        $editor = new FakeRole('editor', ['posts.update'], rank: 5);
        $publisher = new FakeRole('publisher', ['posts.publish'], rank: 4);
        $this->actor = new FakeActor(1, $editor);
        $this->multiActor = new FakeMultiRoleActor(1, $editor, $publisher);
        $this->largeActor = new FakeActor(1, new FakeRole('editor', array_map(
            static fn (int $id): string => 'permission.' . $id,
            range(1, 512),
        )));
        $this->ownerContext = new Context([OwnerPolicy::ATTR_RESOURCE => new FakeOwnable($this->actor->uuid)]);
        $this->hierarchyContext = new Context([HierarchyPolicy::ATTR_TARGET => new FakeRole('user', rank: 1)]);
        $this->role = new RolePolicy('editor');
        $this->deniedRole = new RolePolicy('admin');
        $this->allPermissions = new PermissionPolicy([new FakePermission('posts.update'), new FakePermission('posts.publish')]);
        $this->anyPermission = new PermissionPolicy([new FakePermission('posts.delete'), new FakePermission('posts.update')], ContainsMode::ANY);
        $this->missingPermission = new PermissionPolicy(new FakePermission('posts.delete'));
        $this->largePermission = new PermissionPolicy(new FakePermission('permission.512'));
        $this->hierarchy = new HierarchyPolicy();
        $this->owner = new OwnerPolicy();
        $this->callback = new CallbackPolicy(static fn (object $actor, ContextInterface $context): true|DenyReason =>
            $actor instanceof FakeActor ? true : new DenyReason('Not an actor')
        );
        $this->all = new AllOf($this->role, $this->anyPermission);
        $this->one = new OneOf($this->deniedRole, $this->role);
    }

    public function benchRoleAllow(): void
    {
        $this->role->enforce($this->actor, $this->context);
    }

    public function benchRoleCollection(): void
    {
        $this->role->enforce($this->multiActor, $this->context);
    }

    public function benchRoleDeny(): void
    {
        $this->deniedRole->enforce($this->actor, $this->context);
    }

    public function benchPermissionAll(): void
    {
        $this->allPermissions->enforce($this->multiActor, $this->context);
    }

    public function benchPermissionAny(): void
    {
        $this->anyPermission->enforce($this->actor, $this->context);
    }

    public function benchPermissionDeny(): void
    {
        $this->missingPermission->enforce($this->actor, $this->context);
    }

    #[Bench\Revs(100)]
    public function benchPermissionLarge(): void
    {
        $this->largePermission->enforce($this->largeActor, $this->context);
    }

    public function benchHierarchy(): void
    {
        $this->hierarchy->enforce($this->multiActor, $this->hierarchyContext);
    }

    public function benchOwner(): void
    {
        $this->owner->enforce($this->actor, $this->ownerContext);
    }

    public function benchCallback(): void
    {
        $this->callback->enforce($this->actor, $this->context);
    }

    public function benchAllOf(): void
    {
        $this->all->enforce($this->actor, $this->context);
    }

    public function benchOneOf(): void
    {
        $this->one->enforce($this->actor, $this->context);
    }
}
