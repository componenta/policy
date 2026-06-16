<?php

declare(strict_types=1);

namespace Componenta\Policy;

/**
 * Fallback used by {@see PolicyEnforcer} when no policy is registered for an action.
 */
enum MissingPolicyBehavior
{
    case ALLOW;
    case DENY;
}
