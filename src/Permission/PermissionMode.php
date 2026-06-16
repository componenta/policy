<?php

declare(strict_types=1);

namespace Componenta\Policy\Permission;

/**
 * Evaluation mode for {@see \Componenta\Policy\Policies\PermissionPolicy} when multiple permissions are required.
 */
enum PermissionMode: string
{
    /** Every listed permission must be granted. */
    case ALL = 'all';

    /** At least one of the listed permissions must be granted. */
    case ANY = 'any';
}
