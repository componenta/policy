<?php

declare(strict_types=1);

namespace Componenta\Policy\Tests\Fixture;

use Componenta\Policy\Context\ContextInterface;
use Componenta\Policy\Exception\DenyReason;
use Componenta\Policy\PolicyInterface;

/**
 * Spy policy that stores the context it was invoked with so tests can inspect
 * what the enforcer actually passed down after its own context preparation.
 */
final class ContextCapturingPolicy implements PolicyInterface
{
    public ?ContextInterface $lastContext = null;

    public function enforce(object $actor, ContextInterface $context): true|DenyReason
    {
        $this->lastContext = $context;

        return true;
    }
}
