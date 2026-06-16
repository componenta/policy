<?php

declare(strict_types=1);

namespace Componenta\Policy\Exception;

final class MissingPolicyContextAttributeException extends PolicyException
{
    public function __construct(
        public readonly string $attribute,
        public readonly ?string $expectedType = null,
    ) {
        parent::__construct(
            $expectedType === null
                ? sprintf('Required policy context attribute "%s" is missing.', $attribute)
                : sprintf(
                'Required policy context attribute "%s" is missing. Expected %s.',
                $attribute,
                $expectedType,
            )
        );
    }
}