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

it('rejects direct and indirect cycles without mutating the configured chain', function (string $class): void {
    $policy = RecordingPolicy::deny();
    $provider = new $class([new FakeProvider(['x' => $policy])]);

    foreach (['add', 'prepend'] as $method) {
        expect(fn () => $provider->$method($provider))
            ->toThrow(InvalidArgumentException::class, 'Circular policy provider registration.');

        foreach ([CompositePolicyProvider::class, AllOfPolicyProvider::class, OneOfPolicyProvider::class] as $otherClass) {
            $other = new $otherClass([$provider]);

            expect(fn () => $provider->$method($other))
                ->toThrow(InvalidArgumentException::class, 'Circular policy provider registration.')
                ->and($provider->provideFor('x'))->toBe($policy)
                ->and($other->provideFor('x'))->toBe($policy);
        }
    }

    $middle = new AllOfPolicyProvider([$provider]);
    $outer = new OneOfPolicyProvider([$middle]);

    expect(fn () => $provider->prepend($outer))
        ->toThrow(InvalidArgumentException::class)
        ->and($provider->provideFor('missing'))->toBeNull()
        ->and($outer->provideFor('x'))->toBe($policy);
})->with([
    CompositePolicyProvider::class,
    AllOfPolicyProvider::class,
    OneOfPolicyProvider::class,
]);

it('allows shared acyclic subchains and preserves duplicate registration semantics', function (string $class): void {
    $leaf = new FakeProvider();
    $shared = new $class([$leaf]);
    $provider = new $class([new $class([$shared]), new $class([$shared])]);

    expect($provider->provideFor('missing'))->toBeNull()
        ->and($leaf->calls['missing'] ?? 0)->toBe(2);

    $duplicates = new $class([$leaf, $leaf]);

    expect($duplicates->provideFor('duplicate'))->toBeNull()
        ->and($leaf->calls['duplicate'] ?? 0)->toBe(2);

    $clone = clone $shared;
    $shared->add($clone);

    expect($shared->provideFor('clone'))->toBeNull()
        ->and($leaf->calls['clone'] ?? 0)->toBe(2);
})->with([
    CompositePolicyProvider::class,
    AllOfPolicyProvider::class,
    OneOfPolicyProvider::class,
]);
