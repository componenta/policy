<?php

declare(strict_types=1);

namespace Componenta\Policy\Tests\Fixture;

use Componenta\Policy\PolicyInterface;
use Componenta\Policy\PolicyProviderInterface;

final class FakeProvider implements PolicyProviderInterface
{
    /** @var array<string, int> */
    public array $calls = [];

    /**
     * @param array<string, PolicyInterface> $map
     */
    public function __construct(
        private readonly array $map = [],
    ) {}

    public function provideFor(string $actionId): ?PolicyInterface
    {
        $this->calls[$actionId] = ($this->calls[$actionId] ?? 0) + 1;

        return $this->map[$actionId] ?? null;
    }
}
