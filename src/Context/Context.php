<?php

declare(strict_types=1);

namespace Componenta\Policy\Context;

/**
 * Immutable attribute bag - default {@see ContextInterface} implementation.
 */
final readonly class Context implements ContextInterface
{
    /** @var array<string, mixed> */
    private array $attributes;

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(array $attributes = [])
    {
        // Copy top-level values, not PHP references to the caller's array slots.
        // Values that are objects intentionally retain their identity.
        $snapshot = [];

        foreach ($attributes as $name => $value) {
            $snapshot[$name] = $value;
        }

        $this->attributes = $snapshot;
    }

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
        return new self(array_replace($this->attributes, [$name => $value]));
    }

    public function withAttributes(array $attributes): static
    {
        return new self(array_replace($this->attributes, $attributes));
    }

    public function withoutAttribute(string $name): static
    {
        $attributes = $this->attributes;
        unset($attributes[$name]);

        return new self($attributes);
    }
}