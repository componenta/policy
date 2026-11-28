<?php

declare(strict_types=1);

use Componenta\DI\FactoryInterface;
use Componenta\Policy\Attribute\AllOf;
use Componenta\Policy\Attribute\OneOf;
use Componenta\Policy\Attribute\Policy;
use Componenta\Policy\Context\Context;
use Componenta\Policy\Context\ContextInterface;
use Componenta\Policy\Policies\Allow;
use Componenta\Policy\Policies\Deny;
use Componenta\Policy\PolicyInterface;
use Componenta\Policy\Provider\AttributePolicyProvider;
use Componenta\Policy\Tests\Fixture\AttributeTargets\ChildInheritingBasePolicy;
use Componenta\Policy\Tests\Fixture\AttributeTargets\ChildOverridingBasePolicy;
use Componenta\Policy\Tests\Fixture\AttributeTargets\InjectedPolicy;
use Componenta\Policy\Tests\Fixture\AttributeTargets\Plain;
use Componenta\Policy\Tests\Fixture\AttributeTargets\WithClassAttribute;
use Componenta\Policy\Tests\Fixture\AttributeTargets\WithComposite;
use Componenta\Policy\Tests\Fixture\AttributeTargets\WithPolicyAttribute;
use Componenta\Policy\Tests\Fixture\AttributeTargets\WithSinglePolicy;
use Componenta\Policy\Tests\Fixture\AttributeTargets\WithTwoPolicies;
use Componenta\Policy\Tests\Fixture\FakeActor;
use Componenta\Policy\Tests\Fixture\FakeFactory;
use Componenta\Policy\Tests\Fixture\FakeRole;

beforeEach(function () {
    $this->provider = new AttributePolicyProvider(new FakeFactory());
});

it('returns null for an actionId that is neither a class nor Class::method', function () {
    expect($this->provider->provideFor('posts.create'))->toBeNull();
});

it('returns null when the class has no policy attributes', function () {
    expect($this->provider->provideFor(Plain::class))->toBeNull();
});

it('returns null when the method has no policy attributes', function () {
    expect($this->provider->provideFor(Plain::class . '::action'))->toBeNull();
});

it('returns null when the method does not exist', function () {
    expect($this->provider->provideFor(Plain::class . '::missing'))->toBeNull();
});

it('returns null when the class part of Class::method does not exist', function () {
    expect($this->provider->provideFor('NonExistentClass::method'))->toBeNull();
});

it('returns the method-level attribute policy configured with its attribute arguments', function () {
    // WithSinglePolicy declares #[RolePolicy('admin')] - arguments must reach the constructor.
    $policy = $this->provider->provideFor(WithSinglePolicy::class . '::method');

    $admin = new FakeActor(1, new FakeRole('admin'));
    $other = new FakeActor(2, new FakeRole('user'));

    expect($policy->enforce($admin, new Context()))->toBeTrue()
        ->and($policy->enforce($other, new Context()))->not->toBeTrue();
});

it('combines multiple method-level policy attributes with AND semantics', function () {
    // WithTwoPolicies is annotated with #[RolePolicy('editor')] + #[PermissionPolicy(new FakePermission('posts.update'))].
    // AND-semantics means: neither role nor permission alone is enough - the actor must satisfy both.
    $policy = $this->provider->provideFor(WithTwoPolicies::class . '::method');

    $editorWithoutPermission = new FakeActor(1, new FakeRole('editor'));
    $userWithPermission = new FakeActor(2, new FakeRole('user', ['posts.update']));
    $satisfiesBoth = new FakeActor(3, new FakeRole('editor', ['posts.update']));

    expect($policy->enforce($editorWithoutPermission, new Context()))->not->toBeTrue()
        ->and($policy->enforce($userWithPermission, new Context()))->not->toBeTrue()
        ->and($policy->enforce($satisfiesBoth, new Context()))->toBeTrue();
});

it('resolves a Policy attribute through the DI factory with the configured arguments', function () {
    $policy = $this->provider->provideFor(WithPolicyAttribute::class . '::method');

    expect($policy)->toBeInstanceOf(InjectedPolicy::class)
        ->and($policy->configured)->toBe('value');
});

it('extracts class-level attributes when actionId is the class name', function () {
    // WithClassAttribute has class-level #[RolePolicy('admin')].
    $policy = $this->provider->provideFor(WithClassAttribute::class);

    $admin = new FakeActor(1, new FakeRole('admin'));
    $other = new FakeActor(2, new FakeRole('user'));

    expect($policy->enforce($admin, new Context()))->toBeTrue()
        ->and($policy->enforce($other, new Context()))->not->toBeTrue();
});

it('resolves a OneOf composite with OR semantics over its nested policies', function () {
    // WithComposite has #[OneOf(new RolePolicy('admin'), new RolePolicy('owner'))].
    // Either role alone is enough; a role in neither list is denied.
    $policy = $this->provider->provideFor(WithComposite::class . '::method');

    $admin = new FakeActor(1, new FakeRole('admin'));
    $owner = new FakeActor(2, new FakeRole('owner'));
    $guest = new FakeActor(3, new FakeRole('guest'));

    expect($policy->enforce($admin, new Context()))->toBeTrue()
        ->and($policy->enforce($owner, new Context()))->toBeTrue()
        ->and($policy->enforce($guest, new Context()))->not->toBeTrue();
});

describe('class-hierarchy attribute discovery', function () {
    it('inherits a class-level policy from a parent class when the child declares none', function () {
        $policy = $this->provider->provideFor(ChildInheritingBasePolicy::class);
        $admin = new FakeActor(1, new FakeRole('admin'));
        $other = new FakeActor(2, new FakeRole('user'));

        expect($policy)->toBeInstanceOf(PolicyInterface::class)
            ->and($policy->enforce($admin, new Context()))->toBeTrue()
            ->and($policy->enforce($other, new Context()))->not->toBeTrue();
    });

    it('combines child-class and parent-class policies with AND semantics', function () {
        // Child has #[RolePolicy('moderator')], parent has #[RolePolicy('admin')].
        // AND-semantics means: neither role alone is enough; single-role actors must fail.
        // Checking both a moderator and an admin catches loss of either the parent OR the child chain.
        $policy = $this->provider->provideFor(ChildOverridingBasePolicy::class);

        $moderator = new FakeActor(1, new FakeRole('moderator'));
        $admin = new FakeActor(2, new FakeRole('admin'));

        expect($policy->enforce($moderator, new Context()))->not->toBeTrue()
            ->and($policy->enforce($admin, new Context()))->not->toBeTrue();
    });
});

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class AmbiguousPolicyAttribute extends Policy implements PolicyInterface
{
    public function __construct()
    {
        parent::__construct(Deny::class);
    }

    public function enforce(object $actor, ContextInterface $context): true
    {
        return true;
    }
}

#[AmbiguousPolicyAttribute]
final class AmbiguousPolicyAttributeTarget
{
    #[AmbiguousPolicyAttribute]
    public function direct(): void {}

    #[AllOf(new AmbiguousPolicyAttribute())]
    public function all(): void {}

    #[OneOf(new AmbiguousPolicyAttribute())]
    public function one(): void {}

    #[Allow]
    #[Policy(Deny::class)]
    public function explicit(): void {}
}

it('rejects ambiguous direct and nested declarations without resolving the reference through DI', function (string $action): void {
    $factory = new class implements FactoryInterface {
        public int $calls = 0;

        public function make(string $entry, array $params = []): object
        {
            ++$this->calls;
            return new $entry(...$params);
        }
    };
    $provider = new AttributePolicyProvider($factory);

    expect(fn () => $provider->provideFor($action))
        ->toThrow(InvalidArgumentException::class, 'Ambiguous policy declaration')
        ->and($factory->calls)->toBe(0);
})->with([
    AmbiguousPolicyAttributeTarget::class,
    AmbiguousPolicyAttributeTarget::class . '::direct',
    AmbiguousPolicyAttributeTarget::class . '::all',
    AmbiguousPolicyAttributeTarget::class . '::one',
]);

it('preserves AND semantics when direct policies and references are declared separately', function (): void {
    $policy = $this->provider->provideFor(AmbiguousPolicyAttributeTarget::class . '::explicit');

    expect($policy?->enforce(new stdClass(), new Context()))->not->toBeTrue();
});
