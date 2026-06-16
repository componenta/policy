<?php

declare(strict_types=1);

use Componenta\Policy\Provider\CompositePolicyProvider;
use Componenta\Policy\Tests\Fixture\FakeProvider;
use Componenta\Policy\Tests\Fixture\RecordingPolicy;

it('returns the first non-null policy across providers', function () {
    $secondPolicy = RecordingPolicy::allow();

    $composite = new CompositePolicyProvider([
        new FakeProvider(),
        new FakeProvider(['x' => $secondPolicy]),
        new FakeProvider(['x' => RecordingPolicy::deny()]),
    ]);

    expect($composite->provideFor('x'))->toBe($secondPolicy);
});

it('does not query providers after the first match', function () {
    $first = new FakeProvider(['x' => RecordingPolicy::allow()]);
    $second = new FakeProvider(['x' => RecordingPolicy::allow()]);

    $composite = new CompositePolicyProvider([$first, $second]);
    $composite->provideFor('x');

    expect($first->calls['x'] ?? 0)->toBe(1)
        ->and($second->calls['x'] ?? 0)->toBe(0);
});

it('returns null when no provider has a policy for the action', function () {
    $composite = new CompositePolicyProvider([new FakeProvider(), new FakeProvider()]);

    expect($composite->provideFor('x'))->toBeNull();
});

it('prepend() places the new provider at the front of the chain', function () {
    $frontPolicy = RecordingPolicy::deny();
    $backPolicy = RecordingPolicy::allow();

    $composite = new CompositePolicyProvider([new FakeProvider(['x' => $backPolicy])]);
    $composite->prepend(new FakeProvider(['x' => $frontPolicy]));

    // The resolved policy must be the one from the prepended (front) provider.
    expect($composite->provideFor('x'))->toBe($frontPolicy);
});

it('add() places the new provider at the back of the chain', function () {
    $backPolicy = RecordingPolicy::allow();

    // Front provider has no policy for 'x'; composite must fall through to the appended one.
    $composite = new CompositePolicyProvider([new FakeProvider()]);
    $composite->add(new FakeProvider(['x' => $backPolicy]));

    expect($composite->provideFor('x'))->toBe($backPolicy);
});
