<?php

declare(strict_types=1);

use Componenta\Policy\Context\Context;
use Componenta\Policy\Policies\PermissionPolicy;
use Componenta\Policy\Policies\RolePolicy;
use Componenta\Policy\Provider\AttributePolicyProvider;
use Componenta\Policy\Provider\CompiledPolicyProvider;
use Componenta\Policy\Tests\Fixture\AttributeTargets\ChildOverridingBasePolicy;
use Componenta\Policy\Tests\Fixture\AttributeTargets\InjectedPolicy;
use Componenta\Policy\Tests\Fixture\AttributeTargets\Plain;
use Componenta\Policy\Tests\Fixture\AttributeTargets\WithClassAttribute;
use Componenta\Policy\Tests\Fixture\AttributeTargets\WithComposite;
use Componenta\Policy\Tests\Fixture\AttributeTargets\WithPolicyAttribute;
use Componenta\Policy\Tests\Fixture\AttributeTargets\WithTwoPolicies;
use Componenta\Policy\Tests\Fixture\FakeActor;
use Componenta\Policy\Tests\Fixture\FakeFactory;
use Componenta\Policy\Tests\Fixture\FakePermission;
use Componenta\Policy\Tests\Fixture\FakeRole;

function compiledPolicyProviderForTests(array $classes): CompiledPolicyProvider
{
    return new CompiledPolicyProvider(
        new FakeFactory(),
        compiledPolicyMapForTests($classes),
    );
}

function compiledPolicyMapForTests(array $classes): array
{
    $map = [];

    foreach ($classes as $class) {
        match ($class) {
            WithClassAttribute::class => $map[WithClassAttribute::class] = [
                'kind' => 'direct',
                'class' => RolePolicy::class,
                'arguments' => ['admin'],
            ],
            WithTwoPolicies::class => $map[WithTwoPolicies::class . '::method'] = [
                'kind' => 'all_of',
                'policies' => [
                    [
                        'kind' => 'direct',
                        'class' => RolePolicy::class,
                        'arguments' => ['editor'],
                    ],
                    [
                        'kind' => 'direct',
                        'class' => PermissionPolicy::class,
                        'arguments' => [new FakePermission('posts.update')],
                    ],
                ],
            ],
            WithComposite::class => $map[WithComposite::class . '::method'] = [
                'kind' => 'one_of',
                'policies' => [
                    [
                        'kind' => 'direct',
                        'class' => RolePolicy::class,
                        'arguments' => ['admin'],
                    ],
                    [
                        'kind' => 'direct',
                        'class' => RolePolicy::class,
                        'arguments' => ['owner'],
                    ],
                ],
            ],
            WithPolicyAttribute::class => $map[WithPolicyAttribute::class . '::method'] = [
                'kind' => 'factory',
                'policy' => InjectedPolicy::class,
                'arguments' => ['configured' => 'value'],
            ],
            ChildOverridingBasePolicy::class => $map[ChildOverridingBasePolicy::class] = [
                'kind' => 'all_of',
                'policies' => [
                    [
                        'kind' => 'direct',
                        'class' => RolePolicy::class,
                        'arguments' => ['moderator'],
                    ],
                    [
                        'kind' => 'direct',
                        'class' => RolePolicy::class,
                        'arguments' => ['admin'],
                    ],
                ],
            ],
            default => null,
        };
    }

    return $map;
}

describe('Provider\CompiledPolicyProvider', function () {
    it('returns null for actions missing from the compiled map', function () {
        $provider = compiledPolicyProviderForTests([Plain::class]);

        expect($provider->provideFor(Plain::class))->toBeNull()
            ->and($provider->provideFor('unknown.action'))->toBeNull();
    });

    it('resolves class-level policy descriptors without reflection at lookup time', function () {
        $provider = compiledPolicyProviderForTests([WithClassAttribute::class]);

        $policy = $provider->provideFor(WithClassAttribute::class);

        expect($policy?->enforce(new FakeActor(1, new FakeRole('admin')), new Context()))->toBeTrue()
            ->and($policy?->enforce(new FakeActor(2, new FakeRole('user')), new Context()))->not->toBeTrue();
    });

    it('matches AttributePolicyProvider for method-level AND policy attributes', function () {
        $compiled = compiledPolicyProviderForTests([WithTwoPolicies::class]);
        $attribute = new AttributePolicyProvider(new FakeFactory());
        $actionId = WithTwoPolicies::class . '::method';

        $actor = new FakeActor(3, new FakeRole('editor', ['posts.update']));

        expect($compiled->provideFor($actionId)?->enforce($actor, new Context()))
            ->toBe($attribute->provideFor($actionId)?->enforce($actor, new Context()));
    });

    it('resolves OneOf composites from compiled descriptors', function () {
        $provider = compiledPolicyProviderForTests([WithComposite::class]);
        $policy = $provider->provideFor(WithComposite::class . '::method');

        expect($policy?->enforce(new FakeActor(1, new FakeRole('admin')), new Context()))->toBeTrue()
            ->and($policy?->enforce(new FakeActor(2, new FakeRole('owner')), new Context()))->toBeTrue()
            ->and($policy?->enforce(new FakeActor(3, new FakeRole('guest')), new Context()))->not->toBeTrue();
    });

    it('resolves Policy attributes through the DI factory from compiled descriptors', function () {
        $provider = compiledPolicyProviderForTests([WithPolicyAttribute::class]);

        $policy = $provider->provideFor(WithPolicyAttribute::class . '::method');

        expect($policy)->toBeInstanceOf(InjectedPolicy::class)
            ->and($policy->configured)->toBe('value');
    });

    it('keeps class hierarchy policy semantics', function () {
        $provider = compiledPolicyProviderForTests([ChildOverridingBasePolicy::class]);
        $policy = $provider->provideFor(ChildOverridingBasePolicy::class);

        expect($policy?->enforce(new FakeActor(1, new FakeRole('moderator')), new Context()))->not->toBeTrue()
            ->and($policy?->enforce(new FakeActor(2, new FakeRole('admin')), new Context()))->not->toBeTrue();
    });

    it('caches resolved policies per action id', function () {
        $provider = compiledPolicyProviderForTests([WithClassAttribute::class]);

        expect($provider->provideFor(WithClassAttribute::class))
            ->toBe($provider->provideFor(WithClassAttribute::class));
    });

    it('returns null for malformed descriptors so fallback providers can continue', function () {
        $provider = new CompiledPolicyProvider(new FakeFactory(), [
            'broken' => ['kind' => 'direct', 'class' => 'MissingPolicy', 'arguments' => []],
        ]);

        expect($provider->provideFor('broken'))->toBeNull();
    });
});
