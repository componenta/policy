<?php

declare(strict_types=1);

namespace Componenta\Policy\Tests\Fixture\AttributeTargets;

use Componenta\Policy\Policies\RolePolicy;

#[RolePolicy('admin')]
final class WithClassAttribute
{
}
