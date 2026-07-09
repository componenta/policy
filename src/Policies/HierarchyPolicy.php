<?php

declare(strict_types=1);

namespace Componenta\Policy\Policies;

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
 * and expose a role or role collection. This is useful for moderation flows:
 * a higher-ranked role may act on lower-ranked targets, but cannot act on a
 * target that has an equal or higher role among its assigned roles.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
final class HierarchyPolicy extends AbstractPolicy
{
    public const string ATTR_TARGET = 'target';

    public function enforce(object $actor, ContextInterface $context): true|DenyReason
    {
        $actorRoles = $this->extractRole($actor);

        if ($actorRoles === null) {
            throw InvalidPolicyActorException::expected(
                actor: $actor,
                expectedType: RoleAwareInterface::class . '|' . RoleCollectionAwareInterface::class . '|' . RoleInterface::class . '|' . RoleCollectionInterface::class,
            );
        }

        if (!$context->hasAttribute(self::ATTR_TARGET)) {
            throw new MissingPolicyContextAttributeException(
                attribute: self::ATTR_TARGET,
                expectedType: RoleAwareInterface::class . '|' . RoleCollectionAwareInterface::class . '|' . RoleInterface::class . '|' . RoleCollectionInterface::class,
            );
        }

        $target = $context->getAttribute(self::ATTR_TARGET);
        $targetRoles = self::extractTargetRoles($target);

        if ($targetRoles === null) {
            throw InvalidPolicyContextAttributeException::expected(
                attribute: self::ATTR_TARGET,
                value: $target,
                expectedType: RoleAwareInterface::class . '|' . RoleCollectionAwareInterface::class . '|' . RoleInterface::class . '|' . RoleCollectionInterface::class,
            );
        }

        $actorRoleList = self::normalizeRoles($actorRoles);
        $targetRoleList = self::normalizeRoles($targetRoles);

        if ($actorRoleList === []) {
            return $this->deny('Actor has no roles to compare');
        }

        if ($targetRoleList === []) {
            return $this->deny('Target has no roles to compare');
        }

        if (self::outranks($actorRoleList, $targetRoleList)) {
            return true;
        }

        return $this->deny(sprintf(
            'Actor roles "%s" do not outrank target roles "%s"',
            implode(', ', self::roleNames($actorRoleList)),
            implode(', ', self::roleNames($targetRoleList)),
        ));
    }

    private static function extractTargetRoles(mixed $target): RoleInterface|RoleCollectionInterface|null
    {
        if ($target instanceof RoleInterface || $target instanceof RoleCollectionInterface) {
            return $target;
        }

        if ($target instanceof RoleCollectionAwareInterface) {
            return $target->roles;
        }

        if ($target instanceof RoleAwareInterface) {
            return $target->role;
        }

        return null;
    }

    /**
     * @return list<RoleInterface>
     */
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
     *
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