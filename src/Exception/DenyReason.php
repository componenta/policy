<?php

declare(strict_types=1);

namespace Componenta\Policy\Exception;

/**
 * Lightweight denial payload returned by a {@see \Componenta\Policy\PolicyInterface}.
 *
 * The enforcer wraps this into a full {@see AccessDenied} with action-level context.
 */
final readonly class DenyReason implements \Stringable
{
    /**
     * @param string $value Human-readable denial message.
     * @param string $policyClass FQCN of the deciding policy; empty string if unset.
     */
    public function __construct(
        public string $value,
        public string $policyClass = '',
    ) {}

    public function __toString(): string
    {
        return $this->value;
    }
}
