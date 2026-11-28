<?php

declare(strict_types=1);

use Componenta\Policy\PolicyInterface;
use Componenta\Policy\Provider\AllOfPolicyProvider;
use Componenta\Policy\Provider\CompositePolicyProvider;
use Componenta\Policy\Provider\OneOfPolicyProvider;
use Componenta\Policy\Tests\Fixture\FakeProvider;
use Componenta\Policy\Tests\Fixture\RecordingPolicy;

it('rejects non-provider registrations during construction even when they expose the expected method', function (string $class): void {
    $invalid = new class {
        public function provideFor(string $actionId): ?PolicyInterface
        {
            return RecordingPolicy::allow();
        }
    };

    expect(fn () => new $class([$invalid]))->toThrow(TypeError::class)
        ->and(fn () => new $class([null]))->toThrow(TypeError::class)
        ->and(fn () => new $class([new FakeProvider(), $invalid]))->toThrow(TypeError::class);
})->with([
    CompositePolicyProvider::class,
    AllOfPolicyProvider::class,
    OneOfPolicyProvider::class,
]);

it('preserves provider order and identity for keyed registration arrays', function (string $class): void {
    $policy = RecordingPolicy::deny();
    $first = new FakeProvider();
    $second = new FakeProvider(['x' => $policy]);
    $provider = new $class(['first' => $first, 42 => $second]);

    expect($provider->provideFor('x'))->toBe($policy)
        ->and($first->calls['x'] ?? 0)->toBe(1)
        ->and($second->calls['x'] ?? 0)->toBe(1);
})->with([
    CompositePolicyProvider::class,
    AllOfPolicyProvider::class,
    OneOfPolicyProvider::class,
]);
