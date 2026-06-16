<?php

declare(strict_types=1);

namespace Componenta\Policy\Tests\Fixture\AttributeTargets;

use Componenta\Policy\Attribute\Policy;

final class WithPolicyAttribute
{
    #[Policy(InjectedPolicy::class, ['configured' => 'value'])]
    public function method(): void {}
}
