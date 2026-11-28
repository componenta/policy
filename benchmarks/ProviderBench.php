<?php

declare(strict_types=1);

namespace Componenta\Policy\Benchmarks;

use Componenta\Policy\Context\Context;
use Componenta\Policy\Context\ContextInterface;
use Componenta\Policy\Exception\AccessDeniedException;
use Componenta\Policy\Policies\Allow;
use Componenta\Policy\Policies\CallbackPolicy;
use Componenta\Policy\Policies\Deny;
use Componenta\Policy\Policies\RolePolicy;
use Componenta\Policy\PolicyEnforcer;
use Componenta\Policy\Provider\AllOfPolicyProvider;
use Componenta\Policy\Provider\ArrayPolicyProvider;
use Componenta\Policy\Provider\AttributePolicyProvider;
use Componenta\Policy\Provider\CompositePolicyProvider;
use Componenta\Policy\Provider\OneOfPolicyProvider;
use Componenta\Policy\Tests\Fixture\AttributeTargets\WithPolicyAttribute;
use Componenta\Policy\Tests\Fixture\AttributeTargets\WithTwoPolicies;
use Componenta\Policy\Tests\Fixture\FakeActor;
use Componenta\Policy\Tests\Fixture\FakeContainer;
use Componenta\Policy\Tests\Fixture\FakeFactory;
use Componenta\Policy\Tests\Fixture\FakeRole;
use PhpBench\Attributes as Bench;
use Psr\Container\ContainerInterface;

#[Bench\BeforeMethods(['setUp'])]
#[Bench\Revs(1000)]
#[Bench\Iterations(5)]
final class ProviderBench
{
    private ArrayPolicyProvider $map;
    private AttributePolicyProvider $attributes;
    private CompositePolicyProvider $chain;
    private AllOfPolicyProvider $all;
    private OneOfPolicyProvider $one;
    private PolicyEnforcer $enforcer;
    private FakeActor $actor;
    private Context $context;

    public function setUp(): void
    {
        $container = new FakeContainer();
        $this->map = new ArrayPolicyProvider($container, [
            'allow' => new Allow(),
            'deny' => new Deny(),
            'factory' => static fn (ContainerInterface $container): Allow => new Allow(),
        ]);
        $this->attributes = new AttributePolicyProvider(new FakeFactory());
        $this->chain = new CompositePolicyProvider([$this->map, $this->attributes]);
        $extra = new ArrayPolicyProvider($container, ['allow' => new RolePolicy('editor')]);
        $this->all = new AllOfPolicyProvider([$this->map, $extra]);
        $this->one = new OneOfPolicyProvider([$this->map, $extra]);
        $this->enforcer = new PolicyEnforcer($this->chain);
        $this->actor = new FakeActor(1, new FakeRole('editor', ['posts.update']));
        $this->context = new Context(['metadata' => ['revision' => 1]]);
    }

    public function benchArrayObject(): void
    {
        $this->map->provideFor('allow');
    }

    public function benchArrayFactory(): void
    {
        $this->map->provideFor('factory');
    }

    public function benchAttributePolicies(): void
    {
        $this->attributes->provideFor(WithTwoPolicies::class . '::method');
    }

    public function benchAttributeReference(): void
    {
        $this->attributes->provideFor(WithPolicyAttribute::class . '::method');
    }

    public function benchAttributeCallback(): void
    {
        $this->attributes->provideFor(self::class . '::protectedAction');
    }

    public function benchCompositeFallback(): void
    {
        $this->chain->provideFor(WithTwoPolicies::class . '::method');
    }

    public function benchCompositeMiss(): void
    {
        $this->chain->provideFor('unmapped.action');
    }

    public function benchAllOfProviders(): void
    {
        $this->all->provideFor('allow');
    }

    public function benchOneOfProviders(): void
    {
        $this->one->provideFor('allow');
    }

    public function benchCanWithArrayContext(): void
    {
        $this->enforcer->can('allow', $this->actor, ['metadata' => ['revision' => 1]]);
    }

    public function benchCanWithContext(): void
    {
        $this->enforcer->can('allow', $this->actor, $this->context);
    }

    public function benchCheckDenied(): void
    {
        $this->enforcer->check('deny', $this->actor, $this->context);
    }

    public function benchMissingPolicy(): void
    {
        $this->enforcer->can('unmapped.action', $this->actor, $this->context);
    }

    public function benchEnforceAttribute(): void
    {
        $this->enforcer->enforce(WithTwoPolicies::class . '::method', $this->actor, $this->context);
    }

    public function benchEnforceDenied(): void
    {
        try {
            $this->enforcer->enforce('deny', $this->actor, $this->context);
        } catch (AccessDeniedException) {
            return;
        }

        throw new \LogicException('Expected authorization denial.');
    }

    #[CallbackPolicy(self::allowCallback(...))]
    public function protectedAction(): void {}

    private static function allowCallback(object $actor, ContextInterface $context): true
    {
        return true;
    }
}
