<?php

declare(strict_types=1);

namespace Componenta\Policy\Exception;

use Componenta\Policy\Context\ContextInterface;

/**
 * Default {@see AccessDeniedInterface} record. Built by {@see \Componenta\Policy\PolicyEnforcer}
 * when a policy returns {@see DenyReason}.
 */
final readonly class AccessDenied implements AccessDeniedInterface
{
    public function __construct(
        public string $actionId,
        public DenyReason $reason,
        public object $actor,
        public ContextInterface $context,
    ) {}

    public static function fromReason(
        DenyReason $reason,
        string $actionId,
        object $actor,
        ContextInterface $context,
    ): self {
        return new self(
            actionId: $actionId,
            reason: $reason,
            actor: $actor,
            context: $context,
        );
    }
}
