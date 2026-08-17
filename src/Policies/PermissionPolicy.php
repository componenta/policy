<?php

declare(strict_types=1);

namespace Componenta\Policy\Policies;

use Componenta\Policy\Actor\Guest;
use Componenta\Policy\Actor\PermissionAwareInterface;
use Componenta\Policy\Actor\RoleAwareInterface;
use Componenta\Policy\Actor\RoleCollectionAwareInterface;
use Componenta\Policy\Actor\RoleCollectionInterface;
use Componenta\Policy\ContainsMode;
use Componenta\Policy\Context\ContextInterface;
use Componenta\Policy\Exception\DenyReason;
use Componenta\Policy\Exception\InvalidPolicyActorException;
use Componenta\Policy\Permission\PermissionCollection;
use Componenta\Policy\Permission\PermissionCollectionInterface;
use Componenta\Policy\Permission\PermissionInterface;
use InvalidArgumentException;

/**
 * Checks the actor against the required permissions in
 * {@see ContainsMode::ALL} (AND) or {@see ContainsMode::ANY} (OR) mode.
 *
 * The actor may expose permissions directly ({@see PermissionAwareInterface}),
 * through a single role ({@see RoleAwareInterface}), through a role collection
 * ({@see RoleCollectionAwareInterface}), or be a role collection directly.
 * All discovered sources are merged into one effective permission collection;
 * granting a permission via any source is enough.
 *
 * {@see Guest} is a valid anonymous actor and is denied normally. Other actor
 * objects that expose none of the supported permission/role capabilities are
 * treated as a policy integration error.
 *
 * Applicable directly as a PHP attribute.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
final class PermissionPolicy extends AbstractPolicy
{
    private PermissionCollectionInterface $permissions;

    /**
     * @param PermissionInterface|PermissionCollectionInterface|array<array-key, mixed> $permissions
     *
     * @throws InvalidArgumentException If the permission list is empty or contains invalid values.
     */
    public function __construct(
        PermissionInterface|PermissionCollectionInterface|array $permissions,
        private readonly ContainsMode $mode = ContainsMode::ALL,
    ) {
        $this->permissions = self::normalizePermissions($permissions);

        if ($this->permissions->count() === 0) {
            throw new InvalidArgumentException('PermissionPolicy requires at least one permission.');
        }
    }

    public function enforce(object $actor, ContextInterface $context): true|DenyReason
    {
        if ($actor instanceof Guest) {
            return $this->deny('Guest actor has no permissions');
        }

        $held = $this->extractPermissions($actor);

        if ($held === null) {
            throw InvalidPolicyActorException::expected(
                actor: $actor,
                expectedType: PermissionAwareInterface::class . '|' . RoleAwareInterface::class . '|' . RoleCollectionAwareInterface::class . '|' . RoleCollectionInterface::class,
            );
        }

        if ($held->contains($this->permissions, $this->mode)) {
            return true;
        }

        $missing = array_filter(
            $this->permissions->toArray(),
            static fn(PermissionInterface $permission): bool => !$held->contains($permission),
        );

        $names = self::permissionNames($missing);

        return $this->deny(sprintf(
            $this->mode === ContainsMode::ALL
                ? 'Missing required permissions: %s'
                : 'Requires at least one of permissions: %s',
            implode(', ', $names),
        ));
    }

    private function extractPermissions(object $actor): ?PermissionCollectionInterface
    {
        $permissions = new PermissionCollection();
        $foundSource = false;

        if ($actor instanceof PermissionAwareInterface) {
            self::appendPermissions($permissions, $actor->permissions);
            $foundSource = true;
        }

        if ($actor instanceof RoleAwareInterface) {
            self::appendPermissions($permissions, $actor->role->permissions);
            $foundSource = true;
        }

        if ($actor instanceof RoleCollectionAwareInterface) {
            $foundSource = true;

            foreach ($actor->roles as $role) {
                self::appendPermissions($permissions, $role->permissions);
            }
        }

        if ($actor instanceof RoleCollectionInterface) {
            $foundSource = true;

            foreach ($actor as $role) {
                self::appendPermissions($permissions, $role->permissions);
            }
        }

        return $foundSource ? $permissions : null;
    }

    /**
     * @param PermissionInterface|PermissionCollectionInterface|array<array-key, mixed> $permissions
     */
    private static function normalizePermissions(
        PermissionInterface|PermissionCollectionInterface|array $permissions,
    ): PermissionCollectionInterface {
        if ($permissions instanceof PermissionInterface) {
            return new PermissionCollection([$permissions]);
        }

        if ($permissions instanceof PermissionCollectionInterface) {
            return $permissions;
        }

        foreach ($permissions as $permission) {
            if (!$permission instanceof PermissionInterface) {
                throw new InvalidArgumentException('PermissionPolicy expects permissions to implement PermissionInterface.');
            }
        }

        return new PermissionCollection($permissions);
    }

    private static function appendPermissions(PermissionCollection $target, PermissionCollectionInterface $source): void
    {
        foreach ($source as $permission) {
            $target->add($permission);
        }
    }

    /**
     * @param iterable<PermissionInterface> $permissions
     * @return string[]
     */
    private static function permissionNames(iterable $permissions): array
    {
        $names = [];

        foreach ($permissions as $permission) {
            $names[] = $permission->getName();
        }

        return $names;
    }
}
