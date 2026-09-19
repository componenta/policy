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

it('evaluates the current factory result for every policy resolution', function () {
    $current = RecordingPolicy::allow();
    $factory = function () use (&$current): PolicyInterface { return $current; };
    $provider = new ArrayPolicyProvider(new FakeContainer(), ['x' => $factory]);
    $actor = new \stdClass();
    $context = new \Componenta\Policy\Context\Context();

    expect($provider->provideFor('x')->enforce($actor, $context))->toBeTrue();
    $current = RecordingPolicy::deny('Access changed');
    expect($provider->provideFor('x')->enforce($actor, $context))
        ->toEqual(new \Componenta\Policy\Exception\DenyReason('Access changed', RecordingPolicy::class));
});

it('propagates a factory failure after an earlier successful resolution', function () {
    $failure = new RuntimeException('Policy dependencies are unavailable.');
    $resolved = false;
    $provider = new ArrayPolicyProvider(new FakeContainer(), ['x' => function () use (&$resolved, $failure): PolicyInterface {
        if ($resolved) { throw $failure; }
        $resolved = true;
        return RecordingPolicy::allow();
    }]);
    expect($provider->provideFor('x')->enforce(new stdClass(), new \Componenta\Policy\Context\Context()))->toBeTrue();

    try {
        $provider->provideFor('x');
        $this->fail('The current factory failure must propagate.');
    } catch (RuntimeException $error) {
        expect($error)->toBe($failure);
    }
});
