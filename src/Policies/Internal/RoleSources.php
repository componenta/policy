<?php

declare(strict_types=1);

namespace Componenta\Policy\Policies\Internal;

use Componenta\Policy\Actor\RoleAwareInterface;
use Componenta\Policy\Actor\RoleCollectionAwareInterface;
use Componenta\Policy\Actor\RoleCollectionInterface;
use Componenta\Policy\Actor\RoleInterface;

/** @internal Resolves the role sources shared by the built-in role policies. */
final class RoleSources
{
    /** @return array{roles: list<RoleInterface>, collections: list<RoleCollectionInterface>}|null */
    public static function extract(mixed $subject): ?array
    {
        $roles = [];
        $collections = [];

        if ($subject instanceof RoleInterface) {
            $roles[] = $subject;
        }

        if ($subject instanceof RoleAwareInterface) {
            $role = $subject->role;
            if ($role !== $subject) {
                $roles[] = $role;
            }
        }

        if ($subject instanceof RoleCollectionInterface) {
            $collections[] = $subject;
        }

        if ($subject instanceof RoleCollectionAwareInterface) {
            $collection = $subject->roles;
            if ($collection !== $subject) {
                $collections[] = $collection;
            }
        }

        return $roles === [] && $collections === []
            ? null
            : ['roles' => $roles, 'collections' => $collections];
    }
}
