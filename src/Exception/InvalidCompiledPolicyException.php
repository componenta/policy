<?php

declare(strict_types=1);

namespace Componenta\Policy\Exception;

/**
 * Thrown when a compiled policy descriptor exists but cannot be built.
 */
final class InvalidCompiledPolicyException extends PolicyException
{
    public static function forAction(string $actionId, ?\Throwable $previous = null): self
    {
        return new self(
            sprintf('Invalid compiled policy descriptor for action "%s".', $actionId),
            0,
            $previous,
        );
    }
}