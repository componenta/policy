<?php

declare(strict_types=1);

namespace Componenta\Policy\Policies;

use Componenta\Policy\Actor\RoleAwareInterface;
use Componenta\Policy\Context\ContextInterface;
use Componenta\Policy\Exception\DenyReason;
use Componenta\Policy\Exception\InvalidPolicyActorException;
use Componenta\Policy\Exception\InvalidPolicyContextAttributeException;

/**
 * Allows the action when the actor's role strictly outranks the target's role.
 *
 * The target must be supplied in the context under {@see self::ATTR_TARGET}
 * and implement {@see RoleAwareInterface}. Useful for moderation: higher-ranked
 * roles acting on lower-ranked ones.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
final class HierarchyPolicy extends AbstractPolicy
{
    public const string ATTR_TARGET = 'target';

    public function enforce(object $actor, ContextInterface $context): true|DenyReason
    {
        $actorRole = $this->extractRole($actor);

        if ($actorRole === null) {
            throw InvalidPolicyActorException::expected(
                actor: $actor,
                expectedType: RoleAwareInterface::class . '|' . \Componenta\Policy\Actor\RoleInterface::class,
            );
        }

        $target = $context->requireAttribute(self::ATTR_TARGET);

        if (!$target instanceof RoleAwareInterface) {
            throw InvalidPolicyContextAttributeException::expected(
                attribute: self::ATTR_TARGET,
                value: $target,
                expectedType: RoleAwareInterface::class,
            );
        }

        if ($actorRole->outranks($target->role)) {
            return true;
        }

        return $this->deny(sprintf(
            'Actor role "%s" does not outrank target role "%s"',
            $actorRole->name,
            $target->role->name,
        ));
    }
}
