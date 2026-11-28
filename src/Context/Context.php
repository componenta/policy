<?php

declare(strict_types=1);

namespace Componenta\Policy\Context;

use Componenta\Policy\Exception\ContextCapacityException;

/**
 * Immutable attribute bag - default {@see ContextInterface} implementation.
 *
 * Array values are isolated from caller-owned PHP reference cells, including
 * nested and recursive arrays. Objects and resources retain their identity.
 * Array inspection and reference copying have separate bounded work budgets.
 */
final readonly class Context implements ContextInterface
{
    private const int MAX_ARRAY_ENTRIES = 100_000;

    /** @var array<string, mixed> */
    private array $attributes;

    /** @var array<string, true> */
    private array $referencedAttributes;

    /** @param array<string, mixed> $attributes */
    public function __construct(array $attributes = [])
    {
        $snapshot = [];
        $referenced = [];
        $inspectionRemaining = self::MAX_ARRAY_ENTRIES;
        $copyRemaining = self::MAX_ARRAY_ENTRIES;
        self::consumeEntries($attributes, $inspectionRemaining);

        foreach ($attributes as $name => $value) {
            if (is_array($value) && self::containsReferences($value, $inspectionRemaining)) {
                $value = self::duplicateArray($value, $copyRemaining);
                $referenced[$name] = true;
            }

            $snapshot[$name] = $value;
        }

        $this->attributes = $snapshot;
        $this->referencedAttributes = $referenced;
    }

    /** @param array<string, mixed> $attributes */
    public static function create(array $attributes = []): self
    {
        return new self($attributes);
    }

    public function getAttribute(string $name, mixed $default = null): mixed
    {
        $value = $this->attributes[$name] ?? $default;

        if (!isset($this->referencedAttributes[$name]) || !is_array($value)) {
            return $value;
        }

        $copyRemaining = self::MAX_ARRAY_ENTRIES;

        return self::duplicateArray($value, $copyRemaining);
    }

    public function toArray(): array
    {
        $snapshot = $this->attributes;
        $copyRemaining = self::MAX_ARRAY_ENTRIES;

        foreach ($this->referencedAttributes as $name => $_) {
            $value = $snapshot[$name];
            if (is_array($value)) {
                $snapshot[$name] = self::duplicateArray($value, $copyRemaining);
            }
        }

        return $snapshot;
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

    /**
     * Charge each visited array occurrence before allocating a padded copy.
     * PHP copy-on-write can represent an exponentially large logical tree
     * using a small number of physical arrays.
     *
     * @param array<array-key, mixed> $data
     */
    private static function consumeEntries(array $data, int &$remaining): void
    {
        $size = count($data);
        if ($size > $remaining) {
            throw new ContextCapacityException('Context array processing exceeds ' . self::MAX_ARRAY_ENTRIES . ' entries.');
        }

        $remaining -= $size;
    }

    /** @param array<array-key, mixed> $data */
    private static function containsReferences(array $data, int &$remaining): bool
    {
        $pending = [$data];

        while ($pending !== []) {
            $branch = array_pop($pending);
            self::consumeEntries($branch, $remaining);
            // Keep otherwise hidden reference cells alive while inspecting the array.
            $slots = array_pad($branch, count($branch) + 1, null);

            foreach ($branch as $key => $value) {
                if (\ReflectionReference::fromArrayElement($branch, $key) !== null) {
                    return true;
                }

                if (is_array($value)) {
                    $pending[] = $value;
                }
            }

            unset($slots);
        }

        return false;
    }

    /**
     * Rebuild reference cells without retaining caller-owned cells. Object and resource
     * values are deliberately not cloned. An explicit stack preserves recursive arrays.
     *
     * @template TKey of array-key
     * @param array<TKey, mixed> $data
     * @return array<TKey, mixed>
     */
    private static function duplicateArray(array $data, int &$remaining): array
    {
        $copy = [];
        $references = [];
        /** @var list<array{array<array-key, mixed>, array<array-key, mixed>}> $pending */
        $pending = [[$data, &$copy]];

        while ($pending !== []) {
            $frame = array_pop($pending);
            [$source, &$target] = $frame;
            self::consumeEntries($source, $remaining);
            $slots = array_pad($source, count($source) + 1, null);

            foreach ($source as $key => $value) {
                $reference = \ReflectionReference::fromArrayElement($source, $key);

                if ($reference !== null) {
                    $id = $reference->getId();
                    if (array_key_exists($id, $references)) {
                        $target[$key] = &$references[$id];
                        continue;
                    }

                    $references[$id] = is_array($value) ? [] : $value;
                    $target[$key] = &$references[$id];

                    if (is_array($value)) {
                        $pending[] = [$value, &$references[$id]];
                    }
                    continue;
                }

                if (is_array($value)) {
                    $target[$key] = [];
                    $pending[] = [$value, &$target[$key]];
                    continue;
                }

                $target[$key] = $value;
            }

            unset($target, $slots, $reference);
        }

        /** @var array<TKey, mixed> $copy */
        return $copy;
    }
}
