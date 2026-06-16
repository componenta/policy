<?php

declare(strict_types=1);

use Componenta\Policy\Context\Context;
use Componenta\Policy\Exception\DenyReason;
use Componenta\Policy\Policies\AlwaysPolicy;

beforeEach(function () {
    $this->actor = new stdClass();
    $this->context = new Context();
});

it('allows when constructed via allow()', function () {
    expect(AlwaysPolicy::allow()->enforce($this->actor, $this->context))->toBeTrue();
});

it('denies with the given reason when constructed via refused()', function () {
    $result = AlwaysPolicy::refused('maintenance')->enforce($this->actor, $this->context);

    expect($result)->toBeInstanceOf(DenyReason::class)
        ->and($result->value)->toBe('maintenance')
        ->and($result->policyClass)->toBe(AlwaysPolicy::class);
});
