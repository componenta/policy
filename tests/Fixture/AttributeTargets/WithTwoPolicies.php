<?php

declare(strict_types=1);

namespace Componenta\Policy\Tests\Fixture\AttributeTargets;

use Componenta\Policy\Policies\PermissionPolicy;
use Componenta\Policy\Policies\RolePolicy;
use Componenta\Policy\Tests\Fixture\FakePermission;

final class WithTwoPolicies
{
    #[RolePolicy('editor')]
    #[PermissionPolicy(new FakePermission('posts.update'))]
    public function method(): void {}
}
