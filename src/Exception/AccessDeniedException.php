<?php

declare(strict_types=1);

namespace Componenta\Policy\Exception;

/**
 * Thrown by {@see \Componenta\Policy\PolicyEnforcer::enforce()} when access is denied.
 *
 * Defaults the exception code to HTTP 403. The underlying {@see AccessDeniedInterface}
 * is exposed via the readonly {@see self::$denied} property.
 */
final class AccessDeniedException extends \RuntimeException
{
    public function __construct(
        public readonly AccessDeniedInterface $denied,
        int $code = 403,
        ?\Throwable $previous = null,
    ) {
        $message = sprintf(
            'Access denied for action "%s": %s',
            $denied->actionId,
            $denied->reason->value ?: 'No reason provided',
        );

        parent::__construct($message, $code, $previous);
    }

    public static function fromDenied(AccessDeniedInterface $denied): self
    {
        return new self($denied);
    }
}
