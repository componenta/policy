<?php

declare(strict_types=1);

use Componenta\Policy\Actor\PermissionAwareInterface;
use Componenta\Policy\Actor\RoleCollection;
use Componenta\Policy\Actor\RoleCollectionAwareInterface;
use Componenta\Policy\Actor\RoleCollectionInterface;
use Componenta\Policy\Actor\RoleInterface;
use Componenta\Policy\ContainsMode;
use Componenta\Policy\Context\Context;
use Componenta\Policy\Exception\InvalidPermissionCollectionException;
use Componenta\Policy\Exception\InvalidRoleCollectionException;
use Componenta\Policy\Permission\PermissionCollection;
use Componenta\Policy\Permission\PermissionCollectionInterface;
use Componenta\Policy\Permission\PermissionInterface;
use Componenta\Policy\Policies\PermissionPolicy;
use Componenta\Policy\Policies\RolePolicy;
use Componenta\Policy\Tests\Fixture\FakePermission;
use Componenta\Policy\Tests\Fixture\FakeRole;

/** Deliberately violates the documented collection element contract. */
function invalidRoleElements(array $elements): RoleCollectionInterface
{
    return new class($elements) implements RoleCollectionInterface {
        public function __construct(private array $elements) {}
        public function getIterator(): Traversable { yield from $this->elements; }
        public function count(): int { return count($this->elements); }
        public function toArray(): array { return $this->elements; }

        public function contains(
            RoleInterface|RoleCollectionInterface|string $role,
            ContainsMode $mode = ContainsMode::ANY,
        ): bool {
            return false;
        }
    };
}

/** Deliberately violates the documented collection element contract. */
function invalidPermissionElements(array $elements): PermissionCollectionInterface
{
    return new class($elements) implements PermissionCollectionInterface {
        public function __construct(private array $elements) {}
        public function getIterator(): Traversable { yield from $this->elements; }
        public function count(): int { return count($this->elements); }
        public function toArray(): array { return $this->elements; }

        public function contains(
            PermissionInterface|PermissionCollectionInterface|string $permission,
            ContainsMode $mode = ContainsMode::ANY,
        ): bool {
            return false;
        }
    };
}

it('rejects permission grants from objects that merely expose role permissions', function (): void {
    $invalid = (object) [
        'permissions' => new PermissionCollection([new FakePermission('posts.delete')]),
    ];
    $collection = invalidRoleElements([$invalid]);
    $actor = new readonly class($collection) implements RoleCollectionAwareInterface {
        public function __construct(public RoleCollectionInterface $roles) {}
    };
    $policy = new PermissionPolicy(new FakePermission('posts.delete'));
    $context = new Context();

    expect(fn () => $policy->enforce($collection, $context))
        ->toThrow(InvalidRoleCollectionException::class)
        ->and(fn () => $policy->enforce($actor, $context))
        ->toThrow(InvalidRoleCollectionException::class)
        ->and(fn () => $policy->enforce(
            invalidRoleElements([new FakeRole('admin', ['posts.delete']), $invalid]),
            $context,
        ))->toThrow(InvalidRoleCollectionException::class);
});

it('rejects invalid required and held permission elements', function (): void {
    $invalid = new class {
        public function getName(): string { return 'posts.delete'; }
    };
    $collection = invalidPermissionElements([$invalid]);
    $actor = new readonly class($collection) implements PermissionAwareInterface {
        public function __construct(public PermissionCollectionInterface $permissions) {}
    };

    expect(fn () => new PermissionPolicy($collection))
        ->toThrow(InvalidPermissionCollectionException::class)
        ->and(fn () => (new PermissionPolicy(new FakePermission('posts.delete')))->enforce($actor, new Context()))
        ->toThrow(InvalidPermissionCollectionException::class);
});

it('rejects malformed role and permission comparisons in either mode', function (ContainsMode $mode): void {
    $invalidRole = (object) ['name' => 'admin'];
    $invalidPermission = new class {
        public function getName(): string { return 'posts.delete'; }
    };

    expect(fn () => (new RoleCollection([new FakeRole('admin')]))->contains(invalidRoleElements([$invalidRole]), $mode))
        ->toThrow(InvalidRoleCollectionException::class)
        ->and(fn () => (new PermissionCollection([new FakePermission('posts.delete')]))->contains(
            invalidPermissionElements([$invalidPermission]),
            $mode,
        ))->toThrow(InvalidPermissionCollectionException::class);
})->with([ContainsMode::ANY, ContainsMode::ALL]);

it('does not inspect collection elements beyond an already decisive match', function (): void {
    $role = new FakeRole('admin');
    $permission = new FakePermission('posts.delete');
    $roles = new RoleCollection([$role]);
    $permissions = new PermissionCollection([$permission]);

    expect($roles->contains(invalidRoleElements([$role, null]), ContainsMode::ANY))->toBeTrue()
        ->and($permissions->contains(invalidPermissionElements([$permission, null]), ContainsMode::ANY))->toBeTrue()
        ->and($roles->contains(invalidRoleElements([new FakeRole('editor'), null]), ContainsMode::ALL))->toBeFalse()
        ->and($permissions->contains(invalidPermissionElements([new FakePermission('posts.update'), null]), ContainsMode::ALL))->toBeFalse();
});

it('rejects malformed elements read while constructing role denial details', function (): void {
    $policy = new RolePolicy('admin');

    expect(fn () => $policy->enforce(invalidRoleElements([(object) ['name' => 'editor']]), new Context()))
        ->toThrow(InvalidRoleCollectionException::class);
});
