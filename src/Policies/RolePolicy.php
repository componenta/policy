<?php

declare(strict_types=1);

namespace Componenta\Policy\Policies;

use Componenta\Policy\Context\ContextInterface;
use Componenta\Policy\Exception\DenyReason;
use Componenta\Policy\Exception\InvalidPolicyActorException;

/**
 * Allows the action when the actor's role name is in the configured allowlist.
 *
 * The actor must implement {@see \Componenta\Policy\Actor\RoleAwareInterface} (or
 * be a {@see \Componenta\Policy\Actor\RoleInterface} directly).
 *
 * Applicable directly as a PHP attribute; throws if the actor exposes no role
 * because the policy cannot be evaluated.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
final class RolePolicy extends AbstractPolicy
{
    /** @var string[] */
    private readonly array $roles;

    /**
     * @param string|string[] $roles Role name(s) granted access.
     */
    public function __construct(string|array $roles)
    {
        $this->roles = (array) $roles;
    }

    public function enforce(object $actor, ContextInterface $context): true|DenyReason
    {
        $role = $this->extractRole($actor);

        if ($role === null) {
            throw InvalidPolicyActorException::expected(
                actor: $actor,
                expectedType: \Componenta\Policy\Actor\RoleAwareInterface::class . '|' . \Componenta\Policy\Actor\RoleInterface::class,
            );
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
}
