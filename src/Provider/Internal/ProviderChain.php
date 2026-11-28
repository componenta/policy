<?php

declare(strict_types=1);

namespace Componenta\Policy\Provider\Internal;

use Componenta\Policy\PolicyProviderInterface;
use InvalidArgumentException;

/** @internal Shared registration and cycle validation for built-in provider chains. */
abstract class ProviderChain implements PolicyProviderInterface
{
    /** @var list<PolicyProviderInterface> */
    protected array $providers = [];

    /** @param array<array-key, PolicyProviderInterface> $providers Checked in order. */
    final public function __construct(array $providers = [])
    {
        foreach ($providers as $provider) {
            $this->add($provider);
        }
    }

    final public function add(PolicyProviderInterface $provider): void
    {
        $this->assertAcyclic($provider);
        $this->providers[] = $provider;
    }

    final public function prepend(PolicyProviderInterface $provider): void
    {
        $this->assertAcyclic($provider);
        array_unshift($this->providers, $provider);
    }

    private function assertAcyclic(PolicyProviderInterface $provider): void
    {
        if (!$provider instanceof self) {
            return;
        }

        // Only the new edge can introduce a cycle. Shared subchains are valid.
        $pending = [$provider];
        $visited = [];

        while ($pending !== []) {
            $current = array_pop($pending);

            if ($current === $this) {
                throw new InvalidArgumentException('Circular policy provider registration.');
            }

            $id = spl_object_id($current);
            if (isset($visited[$id])) {
                continue;
            }
            $visited[$id] = true;

            foreach ($current->providers as $child) {
                if ($child instanceof self) {
                    $pending[] = $child;
                }
            }
        }
    }
}
