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

it('never falls through to another provider when a registered factory returns an invalid result', function (mixed $result): void {
    $provider = new ArrayPolicyProvider(new FakeContainer(), ['x' => static fn () => $result]);
    $fallback = new \Componenta\Policy\Tests\Fixture\FakeProvider(['x' => RecordingPolicy::allow()]);
    $enforcer = new \Componenta\Policy\PolicyEnforcer(
        new \Componenta\Policy\Provider\CompositePolicyProvider([$provider, $fallback]),
        behavior: \Componenta\Policy\MissingPolicyBehavior::ALLOW,
    );

    expect(fn () => $enforcer->can('x', new stdClass()))
        ->toThrow(UnexpectedValueException::class, 'must return');
    expect($fallback->calls)->toBe([]);
})->with([null, false, 'allow', new stdClass()]);

it('rejects a null registration instead of treating it as a missing action', function (): void {
    $provider = new ArrayPolicyProvider(new FakeContainer(), ['x' => null]);

    expect(fn () => $provider->provideFor('x'))->toThrow(InvalidArgumentException::class);
    expect($provider->provideFor('unknown'))->toBeNull();
});

it('keeps action registrations independent of caller references', function (): void {
    $denial = RecordingPolicy::deny();
    $registration = $denial;
    $provider = new ArrayPolicyProvider(new FakeContainer(), ['x' => &$registration]);

    $registration = RecordingPolicy::allow();

    expect($provider->provideFor('x'))->toBe($denial);
});

it('does not allow replacing a registered callable method through a referenced array slot', function (): void {
    $denial = RecordingPolicy::deny();
    $factory = new class ($denial) {
        public function __construct(private PolicyInterface $denial) {}
        public function deny(ContainerInterface $container): PolicyInterface
        {
            return $this->denial;
        }
        public function allow(ContainerInterface $container): PolicyInterface
        {
            return RecordingPolicy::allow();
        }
    };
    $method = 'deny';
    $provider = new ArrayPolicyProvider(new FakeContainer(), ['x' => [$factory, &$method]]);

    $method = 'allow';

    expect($provider->provideFor('x'))->toBe($denial);
});
