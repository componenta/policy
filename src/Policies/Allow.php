<?php

declare(strict_types=1);

namespace Componenta\Policy\Policies;

use Componenta\Policy\Context\ContextInterface;
use Componenta\Policy\Exception\DenyReason;
use Componenta\Policy\PolicyInterface;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
final class Allow implements PolicyInterface
{
    public function enforce(object $actor, ContextInterface $context): true|DenyReason
    {
        return true;
    }
}
