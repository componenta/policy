<?php

declare(strict_types=1);

namespace Componenta\Policy\Tests\Fixture\AttributeTargets;

use Componenta\Policy\Context\ContextInterface;
use Componenta\Policy\Exception\DenyReason;
use Componenta\Policy\Policies\AbstractPolicy;

final class InjectedPolicy extends AbstractPolicy
{
    public function __construct(
        public readonly string $configured,
    ) {}

    public function enforce(object $actor, ContextInterface $context): true|DenyReason
    {
        return true;
    }
}
