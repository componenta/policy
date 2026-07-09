<?php

declare(strict_types=1);

namespace Componenta\Policy\Tests\Fixture\AttributeTargets;

use Componenta\Policy\Attribute\OneOf;
use Componenta\Policy\Policies\RolePolicy;

final class WithComposite
{
    #[OneOf(
        new RolePolicy('admin'),
        new RolePolicy('owner'),
    )]
    public function method(): void {}
}
