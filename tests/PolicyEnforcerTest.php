<?php

declare(strict_types=1);

use Componenta\Policy\Context\Context;
use Componenta\Policy\Context\ContextFactory;
use Componenta\Policy\Context\ContextFactoryInterface;
use Componenta\Policy\Context\ContextInterface;
use Componenta\Policy\Exception\AccessDeniedException;
use Componenta\Policy\Exception\AccessDenied;
use Componenta\Policy\MissingPolicyBehavior;
use Componenta\Policy\PolicyEnforcer;
use Componenta\Policy\PolicyInterface;
use Componenta\Policy\Tests\Fixture\ContextCapturingPolicy;
use Componenta\Policy\Tests\Fixture\FakeActor;
use Componenta\Policy\Tests\Fixture\FakeProvider;
use Componenta\Policy\Tests\Fixture\FakeRole;
use Componenta\Policy\Tests\Fixture\RecordingPolicy;

/**
 * @param array<string, PolicyInterface> $policies
 */
function policyEnforcerForTests(array $policies = [], MissingPolicyBehavior $behavior = MissingPolicyBehavior::DENY): PolicyEnforcer
{
    return new PolicyEnforcer(new FakeProvider($policies), new ContextFactory(), $behavior);
}

function policyTestActor(): FakeActor
{
    return new FakeActor(1, new FakeRole('admin'));
}

describe('check()', function () {
    it('returns true when the resolved policy allows', function () {
        $result = policyEnforcerForTests(['posts.create' => RecordingPolicy::allow()])
            ->check('posts.create', policyTestActor());

        expect($result)->toBeTrue();
    });

    it('returns AccessDenied carrying action, reason, policy class and actor when a policy denies', function () {
        $actor = policyTestActor();

        $result = policyEnforcerForTests(['posts.delete' => RecordingPolicy::deny('not allowed')])
            ->check('posts.delete', $actor);

        expect($result)->toBeInstanceOf(AccessDenied::class)
            ->and($result->actionId)->toBe('posts.delete')
            ->and($result->reason->value)->toBe('not allowed')
            ->and($result->reason->policyClass)->toBe(RecordingPolicy::class)
            ->and($result->actor)->toBe($actor);
    });

    it('passes array-context attributes through the factory to the policy', function () {
        $spy = new ContextCapturingPolicy();

        policyEnforcerForTests(['x' => $spy])->check('x', policyTestActor(), ['foo' => 'bar']);

        expect($spy->lastContext?->getAttribute('foo'))->toBe('bar');
    });
});

describe('missing policy behavior', function () {
    it('denies by default when no policy matches the action', function () {
        $result = policyEnforcerForTests()->check('unmapped', policyTestActor());

        expect($result)->toBeInstanceOf(AccessDenied::class)
            ->and($result->actionId)->toBe('unmapped');
    });

    it('allows when constructed with ALLOW behavior', function () {
        $enforcer = policyEnforcerForTests([], MissingPolicyBehavior::ALLOW);

        expect($enforcer->check('unmapped', policyTestActor()))->toBeTrue();
    });

    it('accepts a per-call override via array context', function () {
        $result = policyEnforcerForTests()->check('unmapped', policyTestActor(), [
            PolicyEnforcer::ATTR_MISSING_POLICY_BEHAVIOR => MissingPolicyBehavior::ALLOW,
        ]);

        expect($result)->toBeTrue();
    });

    it('accepts a per-call override via Context object', function () {
        $context = new Context([
            PolicyEnforcer::ATTR_MISSING_POLICY_BEHAVIOR => MissingPolicyBehavior::ALLOW,
        ]);

        expect(policyEnforcerForTests()->check('unmapped', policyTestActor(), $context))->toBeTrue();
    });

    it('strips the override attribute before invoking the policy (array context)', function () {
        $spy = new ContextCapturingPolicy();

        policyEnforcerForTests(['x' => $spy])->check('x', policyTestActor(), [
            PolicyEnforcer::ATTR_MISSING_POLICY_BEHAVIOR => MissingPolicyBehavior::ALLOW,
            'foo' => 'bar',
        ]);

        expect($spy->lastContext?->hasAttribute(PolicyEnforcer::ATTR_MISSING_POLICY_BEHAVIOR))->toBeFalse()
            ->and($spy->lastContext?->getAttribute('foo'))->toBe('bar');
    });

    it('strips the override attribute before invoking the policy (Context object)', function () {
        $spy = new ContextCapturingPolicy();

        $context = new Context([
            PolicyEnforcer::ATTR_MISSING_POLICY_BEHAVIOR => MissingPolicyBehavior::ALLOW,
            'foo' => 'bar',
        ]);

        policyEnforcerForTests(['x' => $spy])->check('x', policyTestActor(), $context);

        expect($spy->lastContext?->hasAttribute(PolicyEnforcer::ATTR_MISSING_POLICY_BEHAVIOR))->toBeFalse()
            ->and($spy->lastContext?->getAttribute('foo'))->toBe('bar');
    });
});

describe('enforce()', function () {
    it('does not throw any exception when the policy allows', function () {
        policyEnforcerForTests(['x' => RecordingPolicy::allow()])->enforce('x', policyTestActor());
    })->throwsNoExceptions();

    it('throws AccessDeniedException carrying the denial and HTTP 403 code', function () {
        try {
            policyEnforcerForTests(['x' => RecordingPolicy::deny('nope')])->enforce('x', policyTestActor());
        } catch (AccessDeniedException $e) {
            expect($e->denied->actionId)->toBe('x')
                ->and($e->denied->reason->value)->toBe('nope')
                ->and($e->getCode())->toBe(403);

            return;
        }

        throw new RuntimeException('Expected AccessDeniedException was not thrown');
    });
});

describe('can()', function () {
    it('reduces an allow to true', function () {
        expect(policyEnforcerForTests(['x' => RecordingPolicy::allow()])->can('x', policyTestActor()))->toBeTrue();
    });

    it('reduces a deny to false', function () {
        expect(policyEnforcerForTests(['x' => RecordingPolicy::deny()])->can('x', policyTestActor()))->toBeFalse();
    });
});

describe('immutable configuration', function () {
    it('withProvider returns a new enforcer and leaves the original unchanged', function () {
        $original = policyEnforcerForTests(['x' => RecordingPolicy::allow()]);
        $swapped = $original->withProvider(new FakeProvider(['x' => RecordingPolicy::deny()]));

        expect($original->can('x', policyTestActor()))->toBeTrue()
            ->and($swapped->can('x', policyTestActor()))->toBeFalse();
    });

    it('withBehavior returns a new enforcer and leaves the original unchanged', function () {
        $original = policyEnforcerForTests([], MissingPolicyBehavior::DENY);
        $permissive = $original->withBehavior(MissingPolicyBehavior::ALLOW);

        expect($original->can('unmapped', policyTestActor()))->toBeFalse()
            ->and($permissive->can('unmapped', policyTestActor()))->toBeTrue();
    });

    it('withFactory uses the replacement factory to build contexts for subsequent checks', function () {
        // A custom factory that stamps the actionId into the context - observable via the spy policy.
        $stampingFactory = new class implements ContextFactoryInterface {
            public function create(string $actionId, array $attributes = []): ContextInterface
            {
                return new Context([...$attributes, '__stamped_by_factory' => $actionId]);
            }
        };

        $spy = new ContextCapturingPolicy();
        $enforcer = policyEnforcerForTests(['x' => $spy])->withFactory($stampingFactory);

        $enforcer->check('x', policyTestActor());

        expect($spy->lastContext?->getAttribute('__stamped_by_factory'))->toBe('x');
    });
});
