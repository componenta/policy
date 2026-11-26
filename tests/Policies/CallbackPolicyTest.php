<?php

declare(strict_types=1);

use Componenta\Policy\Context\Context;
use Componenta\Policy\Context\ContextInterface;
use Componenta\Policy\Exception\AccessDenied;
use Componenta\Policy\Exception\AccessDeniedException;
use Componenta\Policy\Exception\CallbackPolicyException;
use Componenta\Policy\Exception\DenyReason;
use Componenta\Policy\Exception\InvalidPolicyActorException;
use Componenta\Policy\Policies\AllOf;
use Componenta\Policy\Policies\CallbackPolicy;
use Componenta\Policy\Policies\OneOf;
use Componenta\Policy\PolicyEnforcer;
use Componenta\Policy\Tests\Fixture\FakeProvider;

it('evaluates the callback only when enforcing and passes the exact current actor and context', function () {
    $calls = [];
    $policy = new CallbackPolicy(static function (object $actor, ContextInterface $context) use (&$calls): true {
        $calls[] = [$actor, $context];

        return true;
    });
    $firstActor = new stdClass();
    $secondActor = new stdClass();
    $firstContext = new Context(['value' => 'first']);
    $secondContext = new Context(['value' => 'second']);

    expect($calls)->toBe([]);
    expect($policy->enforce($firstActor, $firstContext))->toBeTrue();
    expect($policy->enforce($secondActor, $secondContext))->toBeTrue();
    expect($calls)->toBe([[$firstActor, $firstContext], [$secondActor, $secondContext]]);
});

it('preserves the exact denial object and its provenance', function () {
    $reason = new DenyReason('Editing is disabled.', 'ApplicationPolicy');
    $policy = new CallbackPolicy(static fn (): DenyReason => $reason);

    expect($policy->enforce(new stdClass(), new Context()))->toBe($reason);
});

it('rejects callback results that are neither true nor a denial', function (mixed $result) {
    $policy = new CallbackPolicy(static fn (): mixed => $result);

    expect(fn () => $policy->enforce(new stdClass(), new Context()))
        ->toThrow(CallbackPolicyException::class);
})->with([
    'false is not denial syntax' => [false],
    'null' => [null],
    'truthy integer' => [1],
    'truthy string' => ['allow'],
    'array' => [[]],
    'unrelated object' => [new stdClass()],
]);

it('preserves policy-layer failures without converting them into denial', function (string $method) {
    $failure = new InvalidPolicyActorException('RequiredActor', stdClass::class);
    $policy = new CallbackPolicy(static function () use ($failure): never {
        throw $failure;
    });
    $enforcer = new PolicyEnforcer(new FakeProvider(['edit' => $policy]));

    try {
        $enforcer->{$method}('edit', new stdClass());
        test()->fail('The policy evaluation failure must propagate.');
    } catch (InvalidPolicyActorException $caught) {
        expect($caught)->toBe($failure);
    }
})->with(['check', 'enforce', 'can']);

it('wraps foreign callback failures with their original cause', function () {
    $failure = new RuntimeException('Dependency failed.');
    $policy = new CallbackPolicy(static function () use ($failure): never {
        throw $failure;
    });

    try {
        $policy->enforce(new stdClass(), new Context());
        test()->fail('Callback failure must remain observable.');
    } catch (CallbackPolicyException $caught) {
        expect($caught->getPrevious())->toBe($failure);
    }
});

it('reports a callback signature mismatch as a policy evaluation failure', function () {
    $policy = new CallbackPolicy(static function (string $actor): true {
        return true;
    });

    try {
        $policy->enforce(new stdClass(), new Context());
        test()->fail('The incompatible callback signature must fail.');
    } catch (CallbackPolicyException $caught) {
        expect($caught->getPrevious())->toBeInstanceOf(TypeError::class);
    }
});

final readonly class CallbackPolicyActor
{
    public function __construct(public bool $active) {}
}

it('accepts actor-specific callbacks and reports mismatched actors as evaluation errors', function () {
    $policy = new CallbackPolicy(static function (CallbackPolicyActor $actor, ContextInterface $context): true|DenyReason {
        return $actor->active ? true : new DenyReason('Inactive actor.');
    });

    expect($policy->enforce(new CallbackPolicyActor(true), new Context()))->toBeTrue()
        ->and($policy->enforce(new CallbackPolicyActor(false), new Context()))->toBeInstanceOf(DenyReason::class);

    try {
        $policy->enforce(new stdClass(), new Context());
        test()->fail('An incompatible actor must fail evaluation.');
    } catch (CallbackPolicyException $caught) {
        expect($caught->getPrevious())->toBeInstanceOf(TypeError::class);
    }
});

it('keeps detailed, enforced and boolean denial outcomes consistent', function () {
    $reason = new DenyReason('Denied by callback.');
    $policy = new CallbackPolicy(static fn (): DenyReason => $reason);
    $enforcer = new PolicyEnforcer(new FakeProvider(['edit' => $policy]));
    $actor = new stdClass();

    expect($enforcer->check('edit', $actor))->toBeInstanceOf(AccessDenied::class);
    expect($enforcer->can('edit', $actor))->toBeFalse();
    expect(fn () => $enforcer->enforce('edit', $actor))->toThrow(AccessDeniedException::class);
});

it('short-circuits callback policies using existing AND and OR rules', function () {
    $reason = new DenyReason('Denied.');
    $deny = new CallbackPolicy(static fn (): DenyReason => $reason);
    $allow = new CallbackPolicy(static fn (): true => true);
    $unreachable = new CallbackPolicy(static function (): never {
        throw new LogicException('A later policy must not run.');
    });
    $actor = new stdClass();
    $context = new Context();

    expect((new AllOf($deny, $unreachable))->enforce($actor, $context))->toBe($reason);
    expect((new OneOf($allow, $unreachable))->enforce($actor, $context))->toBeTrue();
});

it('does not treat a callback error as an unsuccessful OR alternative', function () {
    $failing = new CallbackPolicy(static function (): never {
        throw new RuntimeException('Cannot evaluate the rule.');
    });
    $allow = new CallbackPolicy(static fn (): true => true);

    expect(fn () => (new OneOf($failing, $allow))->enforce(new stdClass(), new Context()))
        ->toThrow(CallbackPolicyException::class);
});
