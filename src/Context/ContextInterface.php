<?php

declare(strict_types=1);

namespace Componenta\Policy\Context;

use Componenta\Arrayable\Arrayable;

/**
 * Immutable key-value store passed to policies alongside the actor.
 *
 * Carries runtime data (resource, target user, request metadata) that the
 * actor alone does not describe. All mutation-like methods return a new instance.
 */
interface ContextInterface extends Arrayable
{
    public function getAttribute(string $name, mixed $default = null): mixed;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;

    public function hasAttribute(string $name): bool;

    public function withAttribute(string $name, mixed $value): static;

    /**
     * @param array<string, mixed> $attributes Merged on top of the existing attributes.
     */
    public function withAttributes(array $attributes): static;

    public function withoutAttribute(string $name): static;
}