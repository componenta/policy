<?php

declare(strict_types=1);

namespace Componenta\Policy\Tests\Fixture\AttributeTargets;

use Componenta\Policy\Policies\RolePolicy;

final class WithSinglePolicy
{
    #[RolePolicy('admin')]
    public function method(): void {}
}
