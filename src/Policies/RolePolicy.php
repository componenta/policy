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
use InvalidArgumentException;

/**
 * Allows the action when the actor's role name is in the configured allowlist.
 *
 * The actor may expose a single role, a role collection, or be a
 * {@see RoleInterface}/{@see RoleCollectionInterface} directly. {@see Guest}
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
        $roles = (array) $roles;

        foreach ($roles as $role) {
            if (!is_string($role)) {
                throw new InvalidArgumentException(sprintf(
                    'RolePolicy expects role names to be strings; %s given.',
                    get_debug_type($role),
                ));
            }
        }

        $this->roles = $roles;
    }

    public function enforce(object $actor, ContextInterface $context): true|DenyReason
    {
        if ($actor instanceof Guest) {
            return $this->deny('Guest actor has no roles');
        }

        $role = $this->extractRole($actor);

        if ($role === null) {
            throw InvalidPolicyActorException::expected(
                actor: $actor,
                expectedType: RoleAwareInterface::class . '|' . RoleCollectionAwareInterface::class . '|' . RoleInterface::class . '|' . RoleCollectionInterface::class,
            );
        }

        if ($role instanceof RoleCollectionInterface) {
            foreach ($this->roles as $allowedRole) {
                if ($role->contains($allowedRole)) {
                    return true;
                }
            }

            return $this->deny(sprintf(
                'Actor roles "%s" do not include allowed roles: %s',
                implode(', ', self::roleNames($role)),
                implode(', ', $this->roles),
            ));
        }

        if (in_array($role->name, $this->roles, true)) {
            return true;
        }

        return $this->deny(sprintf(
            'Actor role "%s" is not in allowed roles: %s',
            $role->name,
            implode(', ', $this->roles),
        ));
    }

    /** @return string[] */
    private static function roleNames(RoleCollectionInterface $roles): array
    {
        $names = [];

        foreach ($roles as $role) {
            $names[] = $role->name;
        }

        return $names;
    }
}
