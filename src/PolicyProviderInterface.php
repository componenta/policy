<?php

declare(strict_types=1);

namespace Componenta\Policy;

/**
 * Resolves an action identifier to the policy that governs it.
 *
 * Returns `null` when no rule applies; the enforcer then falls back to
 * the configured {@see MissingPolicyBehavior}.
 */
interface PolicyProviderInterface
{
    /**
     * @param string $actionId Domain string (e.g. "posts.create") or "Class::method" / "Class".
     */
    public function provideFor(string $actionId): ?PolicyInterface;
}
