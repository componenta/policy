<?php

declare(strict_types=1);

use Componenta\Policy\PolicyInterface;
use Componenta\Policy\Provider\ArrayPolicyProvider;
use Componenta\Policy\Tests\Fixture\FakeContainer;
use Componenta\Policy\Tests\Fixture\RecordingPolicy;
use Psr\Container\ContainerInterface;

it('returns null when the action is not mapped', function () {
    $provider = new ArrayPolicyProvider(new FakeContainer(), []);

    expect($provider->provideFor('unknown'))->toBeNull();
});

it('returns a pre-instantiated policy as-is', function () {
    $policy = RecordingPolicy::allow();
    $provider = new ArrayPolicyProvider(new FakeContainer(), ['x' => $policy]);

    expect($provider->provideFor('x'))->toBe($policy);
});

it('resolves a callable factory with the container and returns the produced policy', function () {
    $policy = RecordingPolicy::allow();
    $container = new FakeContainer();

    $seen = null;
    $factory = function (ContainerInterface $c) use ($policy, &$seen) {
        $seen = $c;

        return $policy;
    };

    $provider = new ArrayPolicyProvider($container, ['x' => $factory]);

    expect($provider->provideFor('x'))->toBe($policy)
        ->and($seen)->toBe($container);
});

it('caches a factory-resolved policy so subsequent calls reuse the same instance', function () {
    $invocations = 0;
    $factory = function () use (&$invocations): PolicyInterface {
        $invocations++;

        return RecordingPolicy::allow();
    };

    $provider = new ArrayPolicyProvider(new FakeContainer(), ['x' => $factory]);

    $first = $provider->provideFor('x');
    $second = $provider->provideFor('x');

    expect($first)->toBe($second)
        ->and($invocations)->toBe(1);
});
