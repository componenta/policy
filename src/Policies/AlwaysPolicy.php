<?php

declare(strict_types=1);

namespace Componenta\Policy\Policies;

use Componenta\Policy\Context\ContextInterface;
use Componenta\Policy\Exception\DenyReason;

/**
 * Fixed decision regardless of actor or context - useful for feature flags,
 * placeholders, and test doubles.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
final class AlwaysPolicy extends AbstractPolicy
{
    public function __construct(
        private readonly bool $result,
        private readonly string $reason = 'Access denied by static policy',
    ) {}

    public static function allow(): self
    {
        return new self(true);
    }

    public static function refused(string $reason = 'Access denied by static policy'): self
    {
        return new self(false, $reason);
    }

    public function enforce(object $actor, ContextInterface $context): true|DenyReason
    {
        if ($this->result) {
            return true;
        }

        return $this->deny($this->reason);
    }
}
