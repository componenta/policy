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
use Componenta\Policy\Exception\InvalidPolicyContextAttributeException;
use Componenta\Policy\Exception\MissingPolicyContextAttributeException;

/**
 * Allows the action when one of the actor's roles strictly outranks every
 * target role.
 *
 * The target must be supplied in the context under {@see self::ATTR_TARGET}
 * and expose a role or role collection. For role-aware actors and targets
 * exposing both capabilities, both sources participate in the comparison.
 * {@see Guest} is a valid anonymous
 * actor and is denied normally; other actors without a supported role
 * capability are treated as a policy integration error.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
final class HierarchyPolicy extends AbstractPolicy
{
    public const string ATTR_TARGET = 'target';

    public function enforce(object $actor, ContextInterface $context): true|DenyReason
    {
        if (!$context->hasAttribute(self::ATTR_TARGET)) {
            throw new MissingPolicyContextAttributeException(
                attribute: self::ATTR_TARGET,
                expectedType: RoleAwareInterface::class . '|' . RoleCollectionAwareInterface::class . '|' . RoleInterface::class . '|' . RoleCollectionInterface::class,
            );
        }

        $target = $context->getAttribute(self::ATTR_TARGET);
        $targetRoles = self::extractRoles($target);

        if ($targetRoles === null) {
            throw InvalidPolicyContextAttributeException::expected(
                attribute: self::ATTR_TARGET,
                value: $target,
                expectedType: RoleAwareInterface::class . '|' . RoleCollectionAwareInterface::class . '|' . RoleInterface::class . '|' . RoleCollectionInterface::class,
            );
        }

        if ($actor instanceof Guest) {
            return $this->deny('Guest actor has no role hierarchy');
        }

        $actorRoles = self::extractRoles($actor);

        if ($actorRoles === null) {
            throw InvalidPolicyActorException::expected(
                actor: $actor,
                expectedType: RoleAwareInterface::class . '|' . RoleCollectionAwareInterface::class . '|' . RoleInterface::class . '|' . RoleCollectionInterface::class,
            );
        }

        if ($actorRoles === []) {
            return $this->deny('Actor has no roles to compare');
        }

        if ($targetRoles === []) {
            return $this->deny('Target has no roles to compare');
        }

        if (self::outranks($actorRoles, $targetRoles)) {
            return true;
        }

        return $this->deny(sprintf(
            'Actor roles "%s" do not outrank target roles "%s"',
            implode(', ', self::roleNames($actorRoles)),
            implode(', ', self::roleNames($targetRoles)),
        ));
    }

    /** @return list<RoleInterface>|null */
    private static function extractRoles(mixed $subject): ?array
    {
        if ($subject instanceof RoleInterface || $subject instanceof RoleCollectionInterface) {
            return self::normalizeRoles($subject);
        }

        if (!$subject instanceof RoleAwareInterface && !$subject instanceof RoleCollectionAwareInterface) {
            return null;
        }

        $roles = [];

        if ($subject instanceof RoleAwareInterface) {
            $roles[] = $subject->role;
        }

        if ($subject instanceof RoleCollectionAwareInterface) {
            foreach ($subject->roles as $role) {
                $roles[] = $role;
            }
        }

        return $roles;
    }

    /** @return list<RoleInterface> */
    private static function normalizeRoles(RoleInterface|RoleCollectionInterface $roles): array
    {
        if ($roles instanceof RoleInterface) {
            return [$roles];
        }

        $result = [];

        foreach ($roles as $role) {
            $result[] = $role;
        }

        return $result;
    }

    /**
     * @param list<RoleInterface> $actorRoles
     * @param list<RoleInterface> $targetRoles
     */
    private static function outranks(array $actorRoles, array $targetRoles): bool
    {
        foreach ($actorRoles as $actorRole) {
            foreach ($targetRoles as $targetRole) {
                if (!$actorRole->outranks($targetRole)) {
                    continue 2;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * @param list<RoleInterface> $roles
     * @return string[]
     */
    private static function roleNames(array $roles): array
    {
        return array_map(
            static fn(RoleInterface $role): string => $role->name,
            $roles,
        );
    }
}
