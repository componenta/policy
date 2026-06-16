<?php

declare(strict_types=1);

use Componenta\Policy\Context\Context;
use Componenta\Policy\Exception\DenyReason;
use Componenta\Policy\Policies\OneOf;
use Componenta\Policy\Tests\Fixture\RecordingPolicy;

beforeEach(function () {
    $this->actor = new stdClass();
    $this->context = new Context();
});

it('returns true on the first passing policy and short-circuits the rest', function () {
    $first = RecordingPolicy::allow();
    $second = RecordingPolicy::allow();

    $result = (new OneOf($first, $second))->enforce($this->actor, $this->context);

    expect($result)->toBeTrue()
        ->and($first->calls)->toBe(1)
        ->and($second->calls)->toBe(0);
});

it('returns the last denial reason when all inner policies deny', function () {
    $policy = new OneOf(RecordingPolicy::deny('first'), RecordingPolicy::deny('last'));

    $result = $policy->enforce($this->actor, $this->context);

    expect($result)->toBeInstanceOf(DenyReason::class)
        ->and($result->value)->toBe('last');
});

it('denies when constructed from an empty set (existential over empty)', function () {
    expect(OneOf::of([])->enforce($this->actor, $this->context))->toBeInstanceOf(DenyReason::class);
});
