<?php

declare(strict_types=1);

namespace Componenta\Policy\Tests\Fixture;

use Componenta\Policy\Context\ContextInterface;
use Componenta\Policy\Exception\DenyReason;
use Componenta\Policy\PolicyInterface;

/**
 * Policy that records every enforce() call and returns a preconfigured result.
 * Used to verify composite short-circuit behavior without PHPUnit mocks.
 */
final class RecordingPolicy implements PolicyInterface
{
    public int $calls = 0;

    public function __construct(
        private readonly true|DenyReason $result,
    ) {}

    public function enforce(object $actor, ContextInterface $context): true|DenyReason
    {
        $this->calls++;

        return $this->result;
    }

    public static function allow(): self
    {
        return new self(true);
    }

    public static function deny(string $reason = 'denied'): self
    {
        return new self(new DenyReason($reason, self::class));
    }
}
