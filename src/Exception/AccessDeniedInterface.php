<?php

declare(strict_types=1);

namespace Componenta\Policy\Exception;

use Componenta\Policy\Context\ContextInterface;

/**
 * Outcome of a denied authorization check, suitable for logging and audit.
 *
 * The deciding policy's FQCN is available via {@see DenyReason::$policyClass}
 * on {@see self::$reason}.
 */
interface AccessDeniedInterface
{
    public string $actionId { get; }

    public DenyReason $reason { get; }

    public object $actor { get; }

    public ContextInterface $context { get; }
}
