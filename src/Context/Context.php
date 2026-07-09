<?php

declare(strict_types=1);

namespace Componenta\Policy\Context;

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

    public function toArray(): array
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
}