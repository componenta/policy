<?php

declare(strict_types=1);

use Componenta\Policy\Context\Context;
use Componenta\Policy\Exception\DenyReason;
use Componenta\Policy\Policies\AllOf;
use Componenta\Policy\Tests\Fixture\RecordingPolicy;

beforeEach(function () {
    $this->actor = new stdClass();
    $this->context = new Context();
});

it('allows when all inner policies allow', function () {
    $policy = new AllOf(RecordingPolicy::allow(), RecordingPolicy::allow());

    expect($policy->enforce($this->actor, $this->context))->toBeTrue();
});

it('returns the first denial and short-circuits remaining policies', function () {
    $first = RecordingPolicy::deny('first');
    $second = RecordingPolicy::deny('second');

    $policy = new AllOf($first, $second);
    $result = $policy->enforce($this->actor, $this->context);

    expect($result)->toBeInstanceOf(DenyReason::class)
        ->and($result->value)->toBe('first')
        ->and($first->calls)->toBe(1)
        ->and($second->calls)->toBe(0);
});

it('allows when constructed from an empty set (vacuous truth)', function () {
    expect(AllOf::of([])->enforce($this->actor, $this->context))->toBeTrue();
});

it('of() fully consumes the iterable and uses its policies', function () {
    // Mixing allow + deny ensures the iterable is actually consumed - vacuous truth on an empty
    // set would also return true, which would hide a broken of() that drops all inputs.
    $gen = (function () {
        yield RecordingPolicy::allow();
        yield RecordingPolicy::deny('from iterable');
    })();

    $result = AllOf::of($gen)->enforce($this->actor, $this->context);

    expect($result)->toBeInstanceOf(DenyReason::class)
        ->and($result->value)->toBe('from iterable');
});
