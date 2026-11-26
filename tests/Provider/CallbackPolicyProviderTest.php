<?php

declare(strict_types=1);

namespace Componenta\Policy\Tests;

use Closure;
use Componenta\Policy\Context\Context;
use Componenta\Policy\Context\ContextInterface;
use Componenta\Policy\Exception\DenyReason;
use Componenta\Policy\Policies\CallbackPolicy;
use Componenta\Policy\PolicyEnforcer;
use Componenta\Policy\Provider\AttributePolicyProvider;
use Componenta\Policy\Tests\Fixture\FakeFactory;
use Error;
use stdClass;

it('discovers native callback policies without evaluating them during resolution', function () {
    NativeCallbackProbe::$evaluations = 0;
    $provider = new AttributePolicyProvider(new FakeFactory());
    $policy = $provider->provideFor(NativeCallbackAction::class);

    expect(NativeCallbackProbe::$evaluations)->toBe(0);
    expect($policy?->enforce(new stdClass(), new Context()))->toBeTrue();
    expect(NativeCallbackProbe::$evaluations)->toBe(1);
});

it('combines repeated method callback attributes with AND semantics', function () {
    $enforcer = new PolicyEnforcer(new AttributePolicyProvider(new FakeFactory()));
    $action = NativeCallbackMethods::class . '::edit';

    expect($enforcer->can($action, new stdClass(), ['first' => true, 'second' => false]))->toBeFalse();
    expect($enforcer->can($action, new stdClass(), ['first' => false, 'second' => true]))->toBeFalse();
    expect($enforcer->can($action, new stdClass(), ['first' => true, 'second' => true]))->toBeTrue();
});

it('retains each using class scope across repeated trait callback resolution and actors', function () {
    $enforcer = new PolicyEnforcer(new AttributePolicyProvider(new FakeFactory()));
    $firstActor = new stdClass();
    $secondActor = new stdClass();

    foreach ([FirstTraitCallbackAction::class, SecondTraitCallbackAction::class, FirstTraitCallbackAction::class, SecondTraitCallbackAction::class] as $action) {
        expect($enforcer->can($action, $firstActor, ['expected' => $action, 'actor' => $firstActor]))->toBeTrue();
        expect($enforcer->can($action, $secondActor, ['expected' => $action, 'actor' => $firstActor]))->toBeFalse();
        $other = $action === FirstTraitCallbackAction::class ? SecondTraitCallbackAction::class : FirstTraitCallbackAction::class;
        expect($enforcer->can($action, $firstActor, ['expected' => $other, 'actor' => $firstActor]))->toBeFalse();
    }
});

it('retains native visibility rejection for an inaccessible private callback', function () {
    $provider = new AttributePolicyProvider(new FakeFactory());

    expect(fn () => $provider->provideFor(InaccessibleCallbackAction::class))->toThrow(Error::class);
});

final class NativeCallbackProbe
{
    public static int $evaluations = 0;
}

#[CallbackPolicy(static function (object $actor, ContextInterface $context): true {
    ++NativeCallbackProbe::$evaluations;

    return true;
})]
final class NativeCallbackAction {}

final class NativeCallbackMethods
{
    #[CallbackPolicy(static function (object $actor, ContextInterface $context): true|DenyReason {
        return $context->getAttribute('first') === true ? true : new DenyReason('First condition denied.');
    })]
    #[CallbackPolicy(static function (object $actor, ContextInterface $context): true|DenyReason {
        return $context->getAttribute('second') === true ? true : new DenyReason('Second condition denied.');
    })]
    public function edit(): void {}
}

trait SharedPolicyCallback
{
    private const Closure CHECK = self::decide(...);
}

#[CallbackPolicy(self::CHECK)]
final class FirstTraitCallbackAction
{
    use SharedPolicyCallback;

    private static function decide(object $actor, ContextInterface $context): true|DenyReason
    {
        return $context->getAttribute('expected') === self::class && $context->getAttribute('actor') === $actor
            ? true
            : new DenyReason('First class denied.');
    }
}

#[CallbackPolicy(self::CHECK)]
final class SecondTraitCallbackAction
{
    use SharedPolicyCallback;

    private static function decide(object $actor, ContextInterface $context): true|DenyReason
    {
        return $context->getAttribute('expected') === self::class && $context->getAttribute('actor') === $actor
            ? true
            : new DenyReason('Second class denied.');
    }
}

#[CallbackPolicy(FirstTraitCallbackAction::decide(...))]
final class InaccessibleCallbackAction {}

#[CallbackPolicy('strlen')]
final class InvalidCallbackArgumentAction {}

it('preserves a native callback attribute argument type error', function (): void {
    $provider = new AttributePolicyProvider(new FakeFactory());

    expect(fn () => $provider->provideFor(InvalidCallbackArgumentAction::class))->toThrow(\TypeError::class);
});
