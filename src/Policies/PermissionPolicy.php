<?php

declare(strict_types=1);

namespace Componenta\Policy\Policies;

use Componenta\Policy\Actor\PermissionAwareInterface;
use Componenta\Policy\Actor\RoleAwareInterface;
use Componenta\Policy\Context\ContextInterface;
use Componenta\Policy\Exception\DenyReason;
use Componenta\Policy\Exception\InvalidPolicyActorException;
use Componenta\Policy\Permission\CompositePermissionCollection;
use Componenta\Policy\Permission\PermissionInterface;
use Componenta\Policy\Permission\PermissionMode;
use InvalidArgumentException;

/**
 * Checks the actor against the required permissions, in
 * {@see PermissionMode::ALL} (AND) or {@see PermissionMode::ANY} (OR) mode.
 *
 * The actor may expose permissions directly ({@see PermissionAwareInterface}),
 * through its role ({@see RoleAwareInterface}), or both. The two sources are
 * merged into a single {@see CompositePermissionCollection} - granting a
 * permission via either source is enough.
 *
 * Applicable directly as a PHP attribute; throws when the actor exposes
 * neither a permission set nor a role because the policy cannot be evaluated.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
class PermissionPolicy extends AbstractPolicy
{
    /** @var PermissionInterface[] */
    private readonly array $permissions;

    /**
     * @param PermissionInterface|PermissionInterface[] $permissions
     *
     * @throws InvalidArgumentException If the permission list is empty
     *                                  or contains non-{@see PermissionInterface} values.
     */
    public function __construct(
        PermissionInterface|array $permissions,
        private readonly PermissionMode $mode = PermissionMode::ALL,
    ) {
        if (is_array($permissions)) {
            foreach ($permissions as $i => $permission) {
                if (!$permission instanceof PermissionInterface) {
                    throw new InvalidArgumentException(sprintf(
                        'PermissionPolicy expects PermissionInterface instances, got %s at index %s',
                        get_debug_type($permission),
                        $i,
                    ));
                }
            }
            $this->permissions = array_values($permissions);
        } else {
            $this->permissions = [$permissions];
        }

        if ($this->permissions === []) {
            throw new InvalidArgumentException('PermissionPolicy requires at least one permission');
        }
    }

    public function enforce(object $actor, ContextInterface $context): true|DenyReason
    {
        $held = $this->extractPermissions($actor);

        if ($held === null) {
            throw InvalidPolicyActorException::expected(
                actor: $actor,
                expectedType: PermissionAwareInterface::class . '|' . RoleAwareInterface::class,
            );
        }

        $check = static fn(PermissionInterface $p): bool => $held->contains($p);

        $result = match ($this->mode) {
            PermissionMode::ANY => array_any($this->permissions, $check),
            PermissionMode::ALL => array_all($this->permissions, $check),
        };

        if ($result) {
            return true;
        }

        $missing = array_filter(
            $this->permissions,
            static fn(PermissionInterface $p): bool => !$held->contains($p),
        );

        return $this->deny(sprintf(
            'Missing %s: %s',
            $this->mode === PermissionMode::ALL ? 'required permissions' : 'any of permissions',
            implode(', ', array_map(static fn(PermissionInterface $p) => $p->getName(), $missing)),
        ));
    }

    /**
     * Collects permission sources exposed by the actor - its own set and/or
     * its role's - into a single composite collection.
     *
     * Returns {@code null} when the actor exposes no permission sources at
     * all; an empty composite is never returned.
     */
    private function extractPermissions(object $actor): ?CompositePermissionCollection
    {
        $sources = [];

        if ($actor instanceof PermissionAwareInterface) {
            $sources[] = $actor->permissions;
        }

        if ($actor instanceof RoleAwareInterface) {
            $sources[] = $actor->role->permissions;
        }

        if ($sources === []) {
            return null;
        }

        return new CompositePermissionCollection(...$sources);
    }
}
