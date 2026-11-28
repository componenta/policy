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
use Componenta\Policy\Internal\CollectionElement;
use Componenta\Policy\Permission\PermissionCollection;
use Componenta\Policy\Permission\PermissionCollectionInterface;
use Componenta\Policy\Permission\PermissionInterface;
use Componenta\Policy\Policies\Internal\RoleSources;
use InvalidArgumentException;

/**
 * Checks the actor against the required permissions in
 * {@see ContainsMode::ALL} (AND) or {@see ContainsMode::ANY} (OR) mode.
 *
 * The actor may expose permissions directly ({@see PermissionAwareInterface}),
 * through a single role ({@see RoleAwareInterface}), through a role collection
 * ({@see RoleCollectionAwareInterface}), or be a role collection directly.
 * Permissions are matched against the union of all discovered sources. Each
 * distinct permission collection is inspected once per evaluation.
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
    /** @var list<string> Canonical required names captured at construction. */
    private readonly array $permissions;

    /**
     * @param PermissionInterface|PermissionCollectionInterface|array<array-key, mixed> $permissions
     *
     * @throws InvalidArgumentException If the permission list is empty or contains invalid values.
     */
    public function __construct(
        PermissionInterface|PermissionCollectionInterface|array $permissions,
        private readonly ContainsMode $mode = ContainsMode::ALL,
    ) {
        $this->permissions = array_values(array_unique(self::permissionNames(self::normalizePermissions($permissions))));

        if ($this->permissions === []) {
            throw new InvalidArgumentException('PermissionPolicy requires at least one permission.');
        }
    }

    public function enforce(object $actor, ContextInterface $context): true|DenyReason
    {
        if ($actor instanceof Guest) {
            return $this->deny('Guest actor has no permissions');
        }

        $held = $this->matchPermissions($actor);

        if ($held === null) {
            throw InvalidPolicyActorException::expected(
                actor: $actor,
                expectedType: PermissionAwareInterface::class . '|' . RoleAwareInterface::class . '|' . RoleCollectionAwareInterface::class . '|' . RoleCollectionInterface::class,
            );
        }

        $missing = [];

        foreach ($this->permissions as $name) {
            if ($held[$name]) {
                if ($this->mode === ContainsMode::ANY) {
                    return true;
                }
            } else {
                $missing[] = $name;
            }
        }

        if ($this->mode === ContainsMode::ALL && $missing === []) {
            return true;
        }

        return $this->deny(sprintf(
            $this->mode === ContainsMode::ALL
                ? 'Missing required permissions: %s'
                : 'Requires at least one of permissions: %s',
            implode(', ', $missing),
        ));
    }

    /** @return array<array-key, bool>|null */
    private function matchPermissions(object $actor): ?array
    {
        $sources = RoleSources::extract($actor);
        if (!$actor instanceof PermissionAwareInterface && $sources === null) {
            return null;
        }

        $matches = array_fill_keys($this->permissions, false);
        $visited = [];

        if ($actor instanceof PermissionAwareInterface) {
            self::matchCollection($matches, $visited, $actor->permissions);
        }

        if ($sources !== null) {
            foreach ($sources['roles'] as $role) {
                if ($role !== $actor) {
                    self::matchCollection($matches, $visited, $role->permissions);
                }
            }

            foreach ($sources['collections'] as $collection) {
                foreach ($collection as $role) {
                    self::matchCollection($matches, $visited, CollectionElement::role($role)->permissions);
                }
            }
        }

        return $matches;
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

    /**
     * @param array<array-key, bool> $matches
     * @param array<int, PermissionCollectionInterface> $visited
     */
    private static function matchCollection(array &$matches, array &$visited, PermissionCollectionInterface $source): void
    {
        $id = spl_object_id($source);
        if (isset($visited[$id])) {
            return;
        }

        // Retain the collection until the evaluation ends so IDs cannot be reused.
        $visited[$id] = $source;

        foreach ($source as $permission) {
            $name = CollectionElement::permission($permission)->getName();
            if (isset($matches[$name])) {
                $matches[$name] = true;
            }
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
            $names[] = CollectionElement::permission($permission)->getName();
        }

        return $names;
    }
}
