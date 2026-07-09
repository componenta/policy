<?php

declare(strict_types=1);

namespace Componenta\Policy\Exception;

final class InvalidPolicyContextAttributeException extends PolicyException
{
    public function __construct(
        public readonly string $attribute,
        public readonly string $expectedType,
        public readonly string $actualType,
    ) {
        parent::__construct(sprintf(
            'Policy context attribute "%s" must be %s, got %s.',
            $attribute,
            $expectedType,
            $actualType,
        ));
    }

    public static function expected(
        string $attribute,
        mixed $value,
        string $expectedType,
    ): self {
        return new self(
            attribute: $attribute,
            expectedType: $expectedType,
            actualType: get_debug_type($value),
        );
    }
}