<?php

declare(strict_types=1);

namespace Componenta\Policy\Permission;

/**
 * Named permission.
 *
 * {@see self::getName()} returns the canonical identifier used for equality,
 * collection indexing, and logs - typically in dot notation (e.g. "posts.create").
 */
interface PermissionInterface
{
    public function getName(): string;
}
