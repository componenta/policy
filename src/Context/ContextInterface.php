<?php

declare(strict_types=1);

namespace Componenta\Policy\Context;

use Componenta\Policy\Exception\InvalidPolicyContextAttributeException;
use Componenta\Policy\Exception\MissingPolicyContextAttributeException;

/**
 * Immutable key-value store passed to policies alongside the actor.
 *
 * Carries runtime data (resource, target user, request metadata) that the
 * actor alone does not describe. All mutation-like methods return a new instance.
 */
interface ContextInterface
{
    public function getAttribute(string $name, mixed $default = null): mixed ;

    /**
     * @template T of object
     *
     * @param class-string<T>|ContextValueType|null $type
     *
     * @return ($type is class-string<T> ? T : mixed)
     *
     * @throws MissingPolicyContextAttributeException
     * @throws InvalidPolicyContextAttributeException
     */
    public function requireAttribute(
        string $name,
        string|ContextValueType|null $type = null,
    ): mixed;

    /**
     * @return array<string, mixed>
     */
    public function getAttributes(): array;

    public function hasAttribute(string $name): bool;

    public function withAttribute(string $name, mixed $value): static;

    /**
     * @param array<string, mixed> $attributes Merged on top of the existing attributes.
     */
    public function withAttributes(array $attributes): static;

    public function withoutAttribute(string $name): static;
}
