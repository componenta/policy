<?php

declare(strict_types=1);

use Componenta\Policy\Context\Context;
use Componenta\Policy\Policies\OneOf;
use Componenta\Policy\Provider\OneOfPolicyProvider;
use Componenta\Policy\Tests\Fixture\FakeActor;
use Componenta\Policy\Tests\Fixture\FakeProvider;
use Componenta\Policy\Tests\Fixture\FakeRole;
use Componenta\Policy\Tests\Fixture\RecordingPolicy;

it('returns null when no provider has a policy for the action', function () {
    $provider = new OneOfPolicyProvider([
        new FakeProvider(),
        new FakeProvider(),
    ]);

    expect($provider->provideFor('x'))->toBeNull();
});

it('returns the single matching policy as-is', function () {
    $policy = RecordingPolicy::allow();

    $provider = new OneOfPolicyProvider([
        new FakeProvider(),
        new FakeProvider(['x' => $policy]),
    ]);

    expect($provider->provideFor('x'))->toBe($policy);
});

it('queries every provider and combines all matching policies with OR semantics', function () {
    $firstPolicy = RecordingPolicy::deny('no');
    $secondPolicy = RecordingPolicy::allow();
    $first = new FakeProvider(['x' => $firstPolicy]);
    $second = new FakeProvider();
    $third = new FakeProvider(['x' => $secondPolicy]);

    $provider = new OneOfPolicyProvider([$first, $second, $third]);
    $policy = $provider->provideFor('x');

    expect($policy)->toBeInstanceOf(OneOf::class)
        ->and($first->calls['x'] ?? 0)->toBe(1)
        ->and($second->calls['x'] ?? 0)->toBe(1)
        ->and($third->calls['x'] ?? 0)->toBe(1)
        ->and($policy?->enforce(new FakeActor(1, new FakeRole('admin')), new Context()))->toBeTrue()
        ->and($firstPolicy->calls)->toBe(1)
        ->and($secondPolicy->calls)->toBe(1);
});

it('uses OR semantics when any matching policy allows', function () {
    $denied = RecordingPolicy::deny('blocked');
    $allowed = RecordingPolicy::allow();

    $provider = new OneOfPolicyProvider([
        new FakeProvider(['x' => $denied]),
        new FakeProvider(['x' => $allowed]),
    ]);

    $result = $provider->provideFor('x')?->enforce(new FakeActor(1, new FakeRole('admin')), new Context());

    expect($result)->toBeTrue()
        ->and($denied->calls)->toBe(1)
        ->and($allowed->calls)->toBe(1);
});

it('returns the last denial when all matching policies deny', function () {
    $firstDenied = RecordingPolicy::deny('first');
    $lastDenied = RecordingPolicy::deny('last');

    $provider = new OneOfPolicyProvider([
        new FakeProvider(['x' => $firstDenied]),
        new FakeProvider(['x' => $lastDenied]),
    ]);

    $result = $provider->provideFor('x')?->enforce(new FakeActor(1, new FakeRole('admin')), new Context());

    expect($result)->not->toBeTrue()
        ->and($result?->value)->toBe('last');
});

it('prepend() places the provider at the front of the OR evaluation order', function () {
    $frontPolicy = RecordingPolicy::allow();
    $backPolicy = RecordingPolicy::allow();

    $provider = new OneOfPolicyProvider([new FakeProvider(['x' => $backPolicy])]);
    $provider->prepend(new FakeProvider(['x' => $frontPolicy]));

    $result = $provider->provideFor('x')?->enforce(new FakeActor(1, new FakeRole('admin')), new Context());

    expect($result)->toBeTrue()
        ->and($frontPolicy->calls)->toBe(1)
        ->and($backPolicy->calls)->toBe(0);
});

it('add() places the provider at the back of the OR evaluation order', function () {
    $frontPolicy = RecordingPolicy::allow();
    $backPolicy = RecordingPolicy::allow();

    $provider = new OneOfPolicyProvider([new FakeProvider(['x' => $frontPolicy])]);
    $provider->add(new FakeProvider(['x' => $backPolicy]));

    $result = $provider->provideFor('x')?->enforce(new FakeActor(1, new FakeRole('admin')), new Context());

    expect($result)->toBeTrue()
        ->and($frontPolicy->calls)->toBe(1)
        ->and($backPolicy->calls)->toBe(0);
});
