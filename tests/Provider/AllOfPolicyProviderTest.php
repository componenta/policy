<?php

declare(strict_types=1);

use Componenta\Policy\Context\Context;
use Componenta\Policy\Policies\AllOf;
use Componenta\Policy\Provider\AllOfPolicyProvider;
use Componenta\Policy\Tests\Fixture\FakeActor;
use Componenta\Policy\Tests\Fixture\FakeProvider;
use Componenta\Policy\Tests\Fixture\FakeRole;
use Componenta\Policy\Tests\Fixture\RecordingPolicy;

it('returns null when no provider has a policy for the action', function () {
    $provider = new AllOfPolicyProvider([
        new FakeProvider(),
        new FakeProvider(),
    ]);

    expect($provider->provideFor('x'))->toBeNull();
});

it('returns the single matching policy as-is', function () {
    $policy = RecordingPolicy::allow();

    $provider = new AllOfPolicyProvider([
        new FakeProvider(),
        new FakeProvider(['x' => $policy]),
    ]);

    expect($provider->provideFor('x'))->toBe($policy);
});

it('queries every provider and combines all matching policies with AND semantics', function () {
    $firstPolicy = RecordingPolicy::allow();
    $secondPolicy = RecordingPolicy::allow();
    $first = new FakeProvider(['x' => $firstPolicy]);
    $second = new FakeProvider();
    $third = new FakeProvider(['x' => $secondPolicy]);

    $provider = new AllOfPolicyProvider([$first, $second, $third]);
    $policy = $provider->provideFor('x');

    expect($policy)->toBeInstanceOf(AllOf::class)
        ->and($first->calls['x'] ?? 0)->toBe(1)
        ->and($second->calls['x'] ?? 0)->toBe(1)
        ->and($third->calls['x'] ?? 0)->toBe(1)
        ->and($policy?->enforce(new FakeActor(1, new FakeRole('admin')), new Context()))->toBeTrue()
        ->and($firstPolicy->calls)->toBe(1)
        ->and($secondPolicy->calls)->toBe(1);
});

it('uses AND semantics when any matching policy denies', function () {
    $allowed = RecordingPolicy::allow();
    $denied = RecordingPolicy::deny('blocked');

    $provider = new AllOfPolicyProvider([
        new FakeProvider(['x' => $allowed]),
        new FakeProvider(['x' => $denied]),
    ]);

    $result = $provider->provideFor('x')?->enforce(new FakeActor(1, new FakeRole('admin')), new Context());

    expect($result)->not->toBeTrue()
        ->and($result?->value)->toBe('blocked');
});

it('prepend() places the provider at the front of the AND evaluation order', function () {
    $frontPolicy = RecordingPolicy::deny('front');
    $backPolicy = RecordingPolicy::deny('back');

    $provider = new AllOfPolicyProvider([new FakeProvider(['x' => $backPolicy])]);
    $provider->prepend(new FakeProvider(['x' => $frontPolicy]));

    $result = $provider->provideFor('x')?->enforce(new FakeActor(1, new FakeRole('admin')), new Context());

    expect($result?->value)->toBe('front')
        ->and($frontPolicy->calls)->toBe(1)
        ->and($backPolicy->calls)->toBe(0);
});

it('add() places the provider at the back of the AND evaluation order', function () {
    $frontPolicy = RecordingPolicy::deny('front');
    $backPolicy = RecordingPolicy::deny('back');

    $provider = new AllOfPolicyProvider([new FakeProvider(['x' => $frontPolicy])]);
    $provider->add(new FakeProvider(['x' => $backPolicy]));

    $result = $provider->provideFor('x')?->enforce(new FakeActor(1, new FakeRole('admin')), new Context());

    expect($result?->value)->toBe('front')
        ->and($frontPolicy->calls)->toBe(1)
        ->and($backPolicy->calls)->toBe(0);
});
