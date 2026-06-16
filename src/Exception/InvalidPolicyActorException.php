<?php

declare(strict_types=1);

namespace Componenta\Policy\Exception;

final class InvalidPolicyActorException extends PolicyException
{
    public function __construct(
        public readonly string $expectedType,
        public readonly string $actualType,
    ) {
        parent::__construct(sprintf(
            'Policy expected actor to be instance of %s, got %s.',
            $expectedType,
            $actualType,
        ));
    }

    public static function expected(object $actor, string $expectedType): self
    {
        return new self(
            expectedType: $expectedType,
            actualType: $actor::class,
        );
    }
}