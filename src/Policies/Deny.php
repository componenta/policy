<?php

declare(strict_types=1);

namespace Componenta\Policy\Policies;

use Componenta\Policy\Context\ContextInterface;
use Componenta\Policy\Exception\DenyReason;
use Componenta\Policy\PolicyInterface;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
final readonly class Deny implements PolicyInterface
{
    public function __construct(
        private string $reason = 'Access denied',
    ) {}

    public function enforce(object $actor, ContextInterface $context): DenyReason
    {
        return new DenyReason($this->reason, self::class);
    }
}