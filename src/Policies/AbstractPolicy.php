<?php

declare(strict_types=1);

namespace Componenta\Policy\Policies;

use Componenta\Policy\Actor\RoleAwareInterface;
use Componenta\Policy\Actor\RoleCollectionAwareInterface;
use Componenta\Policy\Actor\RoleCollectionInterface;
use Componenta\Policy\Actor\RoleInterface;
use Componenta\Policy\Exception\DenyReason;
use Componenta\Policy\PolicyInterface;

/**
 * Base class offering common helpers for custom policies: {@see self::deny()}
 * (produces a {@see DenyReason} pre-tagged with the current policy class) and
 * {@see self::extractRole()} (accepts either a {@see RoleAwareInterface} actor
 * or a {@see RoleInterface} directly).
 */
abstract class AbstractPolicy implements PolicyInterface
{
    protected function deny(string $value): DenyReason
    {
        return new DenyReason($value, static::class);
    }

    protected function extractRole(object $actor): null|RoleInterface|RoleCollectionInterface
    {
        if ($actor instanceof RoleInterface) {
            return $actor;
        }

        if ($actor instanceof RoleAwareInterface) {
            return $actor->role;
        }

        if ($actor instanceof RoleCollectionAwareInterface) {
            return $actor->roles;
        }

        return null;
    }
}
