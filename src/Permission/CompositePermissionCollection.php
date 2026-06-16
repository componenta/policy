<?php

declare(strict_types=1);

namespace Componenta\Policy\Permission;

use Generator;

/**
 * Read-only composition of multiple permission collections.
 *
 * Used by {@see \Componenta\Policy\Policies\PermissionPolicy} to merge an actor's
 * own permission set with its role's - without copying either source.
 *
 * Name-based deduplication: if the same permission name appears in more than
 * one source, only the first occurrence is yielded.
 */
final class CompositePermissionCollection implements PermissionCollectionInterface
{
    /**
     * @var PermissionCollectionInterface[]
     */
    private array $collections;

    public function __construct(PermissionCollectionInterface ...$collections)
    {
        $this->collections = $collections;
    }

    public function getIterator(): Generator
    {
        $seen = [];

        foreach ($this->collections as $collection) {
            foreach ($collection as $permission) {
                $name = $permission->getName();

                if (isset($seen[$name])) {
                    continue;
                }

                $seen[$name] = true;
                yield $name => $permission;
            }
        }
    }

    public function count(): int
    {
        $count = 0;
        foreach ($this as $_) {
            $count++;
        }

        return $count;
    }

    public function contains(string|PermissionInterface $permission): bool
    {
        foreach ($this->collections as $collection) {
            if ($collection->contains($permission)) {
                return true;
            }
        }

        return false;
    }

    public function toArray(): array
    {
        return array_keys(iterator_to_array($this->getIterator()));
    }
}