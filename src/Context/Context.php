<?php

declare(strict_types=1);

namespace Componenta\Policy\Context;

use Componenta\Policy\Exception\InvalidPolicyContextAttributeException;
use Componenta\Policy\Exception\MissingPolicyContextAttributeException;

/**
 * Immutable attribute bag - default {@see ContextInterface} implementation.
 */
final readonly class Context implements ContextInterface
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        private array $attributes = [],
    ) {}

    /**
     * @param array<string, mixed> $attributes
     */
    public static function create(array $attributes = []): self
    {
        return new self($attributes);
    }

    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }



    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function hasAttribute(string $name): bool
    {
        return array_key_exists($name, $this->attributes);
    }

    public function withAttribute(string $name, mixed $value): static
    {
        return new self([...$this->attributes, $name => $value]);
    }

    public function withAttributes(array $attributes): static
    {
        return new self([...$this->attributes, ...$attributes]);
    }

    public function withoutAttribute(string $name): static
    {
        $attributes = $this->attributes;
        unset($attributes[$name]);

        return new self($attributes);
    }

    public function requireAttribute(
        string $name,
        string|ContextValueType|null $type = null,
    ): mixed {
        if (!array_key_exists($name, $this->attributes)) {
            throw new MissingPolicyContextAttributeException(
                attribute: $name,
                expectedType: self::typeToString($type),
            );
        }

        $value = $this->attributes[$name];

        if ($type !== null && !$this->matchesType($value, $type)) {
            throw InvalidPolicyContextAttributeException::expected(
                attribute: $name,
                value: $value,
                expectedType: self::typeToString($type),
            );
        }

        return $value;
    }

    private function matchesType(mixed $value, string|ContextValueType $type): bool
    {
        if ($type instanceof ContextValueType) {
            return match ($type) {
                ContextValueType::String => is_string($value),
                ContextValueType::Int => is_int($value),
                ContextValueType::Float => is_float($value),
                ContextValueType::Bool => is_bool($value),
                ContextValueType::Array => is_array($value),
                ContextValueType::Object => is_object($value),
                ContextValueType::Callable => is_callable($value),
                ContextValueType::Iterable => is_iterable($value),
                ContextValueType::Null => $value === null,
            };
        }

        return $value instanceof $type;
    }

    private static function typeToString(string|ContextValueType|null $type): ?string
    {
        return $type instanceof ContextValueType ? $type->value : $type;
    }
}
