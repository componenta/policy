<?php

declare(strict_types=1);

namespace Componenta\Policy\Policies;

use Componenta\Policy\Actor\Guest;
use Componenta\Policy\Actor\RoleAwareInterface;
use Componenta\Policy\Actor\RoleCollectionAwareInterface;
use Componenta\Policy\Actor\RoleCollectionInterface;
use Componenta\Policy\Actor\RoleInterface;
use Componenta\Policy\Context\ContextInterface;
use Componenta\Policy\Exception\DenyReason;
use Componenta\Policy\Exception\InvalidPolicyActorException;
use Componenta\Policy\Internal\CollectionElement;
use Componenta\Policy\Policies\Internal\RoleSources;
use InvalidArgumentException;

/**
 * Allows the action when the actor's role name is in the configured allowlist.
 *
 * The actor may expose a single role, a role collection, or be a
 * {@see RoleInterface}/{@see RoleCollectionInterface} directly. For actors
 * exposing both role capabilities, both sources are considered. {@see Guest}
 * is a valid anonymous actor and is denied normally. Other actors that expose
 * no supported role capability are treated as a policy integration error.
 *
 * Applicable directly as a PHP attribute.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
final class RolePolicy extends AbstractPolicy
{
    /** @var string[] */
    private readonly array $roles;

    /**
     * @param string|string[] $roles Role name(s) granted access.
     *
     * @throws InvalidArgumentException If an array contains a non-string role name.
     */
    public function __construct(string|array $roles)
    {
        $snapshot = [];

        foreach ((array) $roles as $role) {
            if (!is_string($role)) {
                throw new InvalidArgumentException(sprintf(
                    'RolePolicy expects role names to be strings; %s given.',
                    get_debug_type($role),
                ));
            }

            $snapshot[] = $role;
        }

        $this->roles = $snapshot;
    }

    public function enforce(object $actor, ContextInterface $context): true|DenyReason
    {
        if ($actor instanceof Guest) {
            return $this->deny('Guest actor has no roles');
        }

        $sources = RoleSources::extract($actor);

        if ($sources === null) {
            throw InvalidPolicyActorException::expected(
                actor: $actor,
                expectedType: RoleAwareInterface::class . '|' . RoleCollectionAwareInterface::class . '|' . RoleInterface::class . '|' . RoleCollectionInterface::class,
            );
        }

        foreach ($sources['roles'] as $role) {
            if (in_array($role->name, $this->roles, true)) {
                return true;
            }
        }

        foreach ($sources['collections'] as $collection) {
            foreach ($this->roles as $allowedRole) {
                if ($collection->contains($allowedRole)) {
                    return true;
                }
            }
        }

        if (count($sources['roles']) === 1 && $sources['collections'] === []) {
            return $this->deny(sprintf(
                'Actor role "%s" is not in allowed roles: %s',
                $sources['roles'][0]->name,
                implode(', ', $this->roles),
            ));
        }

        $names = [];
        foreach ($sources['roles'] as $role) {
            $names[] = $role->name;
        }
        foreach ($sources['collections'] as $collection) {
            foreach ($collection as $role) {
                $names[] = CollectionElement::role($role)->name;
            }
        }

        return $this->deny(sprintf(
            'Actor roles "%s" do not include allowed roles: %s',
            implode(', ', $names),
            implode(', ', $this->roles),
        ));
    }
}
