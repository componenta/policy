# Componenta Policy

`componenta/policy` is a policy-based authorization library. It keeps access rules out of controllers, command handlers, and other application code: the application passes an action id, actor, and context; the library finds the matching policy and returns a decision.

The package contains contracts, built-in policies, policy providers, `PolicyEnforcer`, PHP attributes, and configuration integration. Attribute discovery during cache warmup and compiled policy maps are provided by [`componenta/policy-app`](../policy-app/README.md). Commands and queries usually invoke policies through [`componenta/cqrs`](../cqrs/README.md).

**[Russian documentation](README.ru.md)

## Installation

```bash
composer require componenta/policy
```

## Dependencies

| Dependency | Purpose |
|---|---|
| PHP `^8.4` | Typed properties with hooks, enums, and strict typing. |
| `componenta/config` | Wires `ConfigProvider` and reads the `policy` config section. |
| `componenta/di` | Creates policies from `#[Policy]` when they need container services. |
| `componenta/identity` | Provides `IdentityInterface` for actors and owned resources. |
| `psr/container` | Used by policy providers and factories. |

## Core Concepts

| Concept | Meaning |
|---|---|
| Actor | User, system subject, or another object on whose behalf an action runs. |
| Action | String identifier for an operation, such as `posts.create` or `App\Controller\PostController::update`. |
| Policy | `PolicyInterface` object that decides whether an actor may perform an action in a context. |
| Context | Immutable data bag for the check: resource, target user, request metadata. |
| Policy provider | `PolicyProviderInterface` object that finds a policy by action id. |
| Denial | Valid check result where actor and context are valid, but authorization rules deny the action. |
| Policy error | Invalid usage or configuration: wrong actor, missing context resource, invalid value type. |

`PolicyEnforcer` accepts any actor as `object`. Each concrete policy validates the capabilities it needs: permissions, role, UUID identity, or resource ownership.

## Quick Start

The smallest example creates a permission, an actor with that permission, registers a policy, and checks an action.

```php
use Componenta\Policy\Actor\PermissionAwareInterface;
use Componenta\Policy\Permission\PermissionCollection;
use Componenta\Policy\Permission\PermissionCollectionInterface;
use Componenta\Policy\Permission\PermissionInterface;
use Componenta\Policy\Policies\PermissionPolicy;
use Componenta\Policy\PolicyEnforcer;
use Componenta\Policy\Provider\ArrayPolicyProvider;

enum PostPermission: string implements PermissionInterface
{
    case CREATE = 'posts.create';

    public function getName(): string
    {
        return $this->value;
    }
}

final readonly class User implements PermissionAwareInterface
{
    public function __construct(
        public PermissionCollectionInterface $permissions,
    ) {}
}

$user = new User(new PermissionCollection([PostPermission::CREATE]));

$provider = new ArrayPolicyProvider($container, [
    'posts.create' => static fn () => new PermissionPolicy(PostPermission::CREATE),
]);

$enforcer = new PolicyEnforcer($provider);

$enforcer->can('posts.create', $user);     // true
$enforcer->enforce('posts.create', $user); // does not throw
```

`$container` is any PSR-11 container. `ArrayPolicyProvider` passes it to callable factories so policies can be created lazily.

## Configuration

Register the package provider in the application configuration:

```php
return [
    new Componenta\Policy\ConfigProvider(),
];
```

`ConfigProvider` registers:

| Service | Purpose |
|---|---|
| `PolicyEnforcer` | Main entry point for authorization checks. |
| `PolicyProviderInterface` | Final policy provider assembled from config, compiled maps, and attributes. |
| `ContextFactoryInterface` | Creates `ContextInterface` for a specific action from attribute arrays. |
| `ActorProviderInterface` | Returns a guest actor by default. Integrations can replace it with a current-user provider. |

Example configuration:

```php
use Componenta\Policy\ConfigKey;
use Componenta\Policy\MissingPolicyBehavior;
use Componenta\Policy\Policies\PermissionPolicy;
use Componenta\Policy\Policies\RolePolicy;

return [
    ConfigKey::POLICY => [
        ConfigKey::POLICIES => [
            'posts.create' => static fn () => new PermissionPolicy(PostPermission::CREATE),
            'admin.access' => static fn () => new RolePolicy('admin'),
        ],
        ConfigKey::PROVIDERS => [
            AppPolicyProvider::class,
        ],
        ConfigKey::MISSING_POLICY_BEHAVIOR => MissingPolicyBehavior::DENY,
    ],
];
```

Configuration keys:

| Key | Value |
|---|---|
| `ConfigKey::POLICY` | Root policy config section. |
| `ConfigKey::POLICIES` | `actionId => PolicyInterface|callable` map used by `ArrayPolicyProvider`. |
| `ConfigKey::PROVIDERS` | List of additional `PolicyProviderInterface` classes resolved from the container. |
| `ConfigKey::MISSING_POLICY_BEHAVIOR` | Missing-policy behavior: `DENY` or `ALLOW`. |
| `ConfigKey::COMPILED_POLICIES` | Compiled policy map from `componenta/policy-app`. |
| `ConfigKey::COMPILED_POLICIES_FILE` | File containing a compiled policy map. |
| `ConfigKey::COMPILED_POLICIES_STRICT` | Throw on invalid compiled descriptors instead of falling back to attributes. |

`PolicyProviderFactory` assembles providers in this order: `ConfigKey::POLICIES` map, custom providers, compiled policies, then `AttributePolicyProvider` as fallback. One provider is returned directly; several providers are wrapped in `CompositePolicyProvider`.

The default factory uses first-match behavior. If an application must combine policies from multiple sources through `AllOfPolicyProvider` or `OneOfPolicyProvider`, register your own `PolicyProviderInterface` service instead of the default factory or build the required composite inside one custom provider.

Integrations can expose the action id through `ActionIdAwareInterface`:

```php
use Componenta\Policy\ActionIdAwareInterface;

final readonly class PublishPostCommand implements ActionIdAwareInterface
{
    public function __construct(
        public string $actionId = 'posts.publish',
    ) {}
}
```

`PolicyEnforcer` itself receives the string `actionId` explicitly. `ActionIdAwareInterface` is for outer layers such as [`componenta/cqrs`](../cqrs/README.md): the default CQRS resolver reads `$object->actionId` when the object implements the interface, otherwise it uses the class name.

## PolicyEnforcer

`PolicyEnforcer` does not contain authorization rules. It receives a policy from `PolicyProviderInterface`, creates context, and calls `PolicyInterface::enforce()`.

```php
$result = $enforcer->check('posts.update', $user, ['resource' => $post]);

if ($result !== true) {
    $logger->warning('Access denied', [
        'action' => $result->actionId,
        'reason' => $result->reason->value,
        'policy' => $result->reason->policyClass,
    ]);
}

$allowed = $enforcer->can('posts.update', $user, ['resource' => $post]);

$enforcer->enforce('posts.update', $user, ['resource' => $post]);
```

Methods:

`check(string $actionId, object $actor, ContextInterface|array $context = []): true|AccessDenied`

Main check method. It returns a detailed result and does not throw on normal authorization denial.

`check()` behavior:

- when `$context` is an array, it is converted to `ContextInterface` through `ContextFactoryInterface::create($actionId, $context)`;
- when context contains `PolicyEnforcer::ATTR_MISSING_POLICY_BEHAVIOR` with a `MissingPolicyBehavior` value, that value applies only to the current call and is removed before the policy is invoked;
- when `PolicyProviderInterface` finds no policy and `MissingPolicyBehavior::ALLOW` is active, the method returns `true`;
- when no policy is found and `MissingPolicyBehavior::DENY` is active, the method returns `AccessDenied`;
- when the policy returns `true`, the method returns `true`;
- when the policy returns `DenyReason`, the method wraps it into `AccessDenied` with `actionId`, actor, and context;
- when the policy cannot be evaluated because of an invalid actor, context, or configuration, the policy exception is not swallowed.

`can(string $actionId, object $actor, ContextInterface|array $context = []): bool`

Boolean shortcut over `check()`. Returns `true` only when the action is allowed. A normal denial becomes `false`, but policy exceptions are not swallowed.

`enforce(string $actionId, object $actor, ContextInterface|array $context = []): void`

Strict check. Returns nothing on success and throws `AccessDeniedException` when `check()` returns a denial. Policy exceptions are not wrapped into `AccessDeniedException`.

`withProvider()`, `withFactory()`, `withBehavior()`

Return a new `PolicyEnforcer` with another policy provider, context factory, or missing-policy behavior. The original object is unchanged.

Policy-layer errors are not converted to access denials. For example, when `OwnerPolicy` requires a resource in context and the resource is missing, a policy exception is thrown. That signals an invalid call or configuration.

## Missing Policy Behavior

The default is `MissingPolicyBehavior::DENY`: if no policy is found for an action, access is denied. This is the safe default for applications.

```php
use Componenta\Policy\MissingPolicyBehavior;

$enforcer = new PolicyEnforcer($provider, behavior: MissingPolicyBehavior::DENY);
```

One call can override the behavior through context:

```php
use Componenta\Policy\PolicyEnforcer;
use Componenta\Policy\MissingPolicyBehavior;

$enforcer->check('health.read', $user, [
    PolicyEnforcer::ATTR_MISSING_POLICY_BEHAVIOR => MissingPolicyBehavior::ALLOW,
]);
```

Values of other types are ignored, and the `PolicyEnforcer` setting remains active.

## Actors, Roles, And Permissions

Built-in policies depend on small interfaces. A user model implements only the capabilities the application needs.

```php
interface PermissionAwareInterface
{
    public PermissionCollectionInterface $permissions { get; }
}

interface RoleAwareInterface
{
    public RoleInterface $role { get; }
}

interface RoleInterface extends PermissionAwareInterface
{
    public string $name { get; }
    public function outranks(RoleAwareInterface|RoleInterface $other): bool;
}

interface RoleCollectionAwareInterface
{
    public RoleCollectionInterface $roles { get; }
}

interface RoleCollectionInterface extends IteratorAggregate, Countable
{
    public function contains(
        RoleInterface|RoleCollectionInterface|string $role,
        ContainsMode $mode = ContainsMode::ANY,
    ): bool;
}
```

`PermissionPolicy` can read permissions directly from `PermissionAwareInterface`, from a single role through `RoleAwareInterface`, and from role collections through `RoleCollectionAwareInterface`. Sources are merged into an effective `PermissionCollection`: holding the permission in any source is enough.

Middleware and other integrations can use two additional contracts:

```php
interface ActorAwareInterface
{
    public ActorInterface $actor { get; }
}

interface ActorProviderInterface
{
    public function getActor(): ?object;
}
```

`ActorAwareInterface` fits commands or queries that already carry the actor. `ActorProviderInterface` is for resolving the current user from an external environment: HTTP request, session, token, or worker process. `getActor()` may return `null` for anonymous access; the integration layer decides how to handle that. Built-in policies still validate the actor interface they need.

A permission is any `PermissionInterface` object:

```php
use Componenta\Policy\Permission\PermissionInterface;

enum PostPermission: string implements PermissionInterface
{
    case CREATE = 'posts.create';
    case EDIT_ANY = 'posts.edit.any';

    public function getName(): string
    {
        return $this->value;
    }
}
```

`PermissionCollectionInterface` is a read-only contract: `contains()`, `toArray()`, iteration, and `count()`. Its `contains()` method accepts `ContainsMode::ANY` and `ContainsMode::ALL` when comparing with another collection. The concrete `PermissionCollection` additionally exposes `add()` and `remove()` for infrastructure, seeders, and fixtures.

```php
$permissions = new PermissionCollection([PostPermission::CREATE]);

$permissions->contains('posts.create');          // true
$permissions->contains(PostPermission::CREATE);  // true
$permissions->add(PostPermission::EDIT_ANY);
$permissions->remove('posts.create');
```

## Context

`ContextInterface` is an immutable key-value store for one authorization check. It extends `Componenta\Arrayable\Arrayable`, so the complete attribute map is exposed through `toArray()`. Arrays passed to `PolicyEnforcer` are converted through `ContextFactoryInterface`.

The context factory receives the action id and initial attributes:

```php
interface ContextFactoryInterface
{
    public function create(string $actionId, array $attributes = []): ContextInterface;
}
```

The default implementation simply creates `Context`, but a custom factory can add action-specific data, for example a resource for `posts.update`.

```php
use Componenta\Policy\Context\Context;

$context = new Context(['resource' => $post]);
$context = $context->withAttribute('ip', '127.0.0.1');

if (!$context->hasAttribute('resource')) {
    // throw a policy exception from the concrete policy
}

$resource = $context->getAttribute('resource');
$ip = $context->getAttribute('ip');
```

Policies validate mandatory context attributes explicitly. Built-in policies throw `MissingPolicyContextAttributeException` when a required attribute is absent and `InvalidPolicyContextAttributeException` when an attribute has the wrong shape.

## Built-In Policies

| Policy | Checks | Requires |
|---|---|---|
| `PermissionPolicy` | Actor holds every listed permission or any listed permission. | `PermissionAwareInterface`, `RoleAwareInterface`, and/or `RoleCollectionAwareInterface`. |
| `RolePolicy` | Any actor role name is in the allowlist. | `RoleAwareInterface`, `RoleCollectionAwareInterface`, `RoleInterface`, or `RoleCollectionInterface`. |
| `HierarchyPolicy` | One actor role outranks every target role. | Actor and `target` expose a role or role collection; `target` is read from context. |
| `OwnerPolicy` | Actor owns the resource. | Actor `IdentityInterface`, resource `OwnableInterface` in context under `resource`. |
| `Allow` | Always allows access. | Nothing. |
| `Deny` | Always denies access with a reason. | Nothing. |
| `AllOf` | Every nested policy must allow the action. | Nothing beyond nested policies. |
| `OneOf` | At least one nested policy must allow the action. | Nothing beyond nested policies. |

### Custom Policy

A policy implements `PolicyInterface`. Extending `AbstractPolicy` is optional, but useful for `deny()` and `extractRole()`.

```php
use Componenta\Policy\Context\ContextInterface;
use Componenta\Policy\Exception\DenyReason;
use Componenta\Policy\Exception\InvalidPolicyActorException;
use Componenta\Policy\Policies\AbstractPolicy;

interface AgeAwareInterface
{
    public int $age { get; }
}

final class MinimumAgePolicy extends AbstractPolicy
{
    public function __construct(
        private readonly int $minimumAge,
    ) {}

    public function enforce(object $actor, ContextInterface $context): true|DenyReason
    {
        if (!$actor instanceof AgeAwareInterface) {
            throw InvalidPolicyActorException::expected(
                actor: $actor,
                expectedType: AgeAwareInterface::class,
            );
        }

        if ($actor->age < $this->minimumAge) {
            return $this->deny("Must be at least {$this->minimumAge} years old");
        }

        return true;
    }
}
```

Return `DenyReason` when the rule was evaluated correctly and access is denied. Throw an exception when the policy cannot be evaluated because the actor, context, or configuration is wrong.

## Policy Providers

### ArrayPolicyProvider

`ArrayPolicyProvider` maps `actionId` to a ready policy or callable factory. The callable receives a PSR-11 container, is resolved lazily, and is cached per action id.

```php
use Componenta\Policy\Provider\ArrayPolicyProvider;
use Componenta\Policy\Policies\OneOf;
use Componenta\Policy\Policies\OwnerPolicy;
use Componenta\Policy\Policies\PermissionPolicy;
use Componenta\Policy\Policies\RolePolicy;

$provider = new ArrayPolicyProvider($container, [
    'posts.create' => static fn () => new PermissionPolicy(PostPermission::CREATE),
    'posts.delete' => static fn () => OneOf::of([
        new RolePolicy('admin'),
        new OwnerPolicy(),
    ]),
]);
```

### AttributePolicyProvider

`AttributePolicyProvider` reads policies from PHP attributes.

```php
use Componenta\Policy\Provider\AttributePolicyProvider;

$attributeProvider = new AttributePolicyProvider($factory);
```

`$factory` is `Componenta\DI\FactoryInterface`; the provider needs it to create policies from `#[Policy(...)]` and domain attributes that extend it. Even when attributes use only direct policies such as `#[PermissionPolicy]`, the factory is still passed to the constructor because the provider supports both modes.

`actionId` format:

| Format | What is read |
|---|---|
| `App\Controller\PostController::update` | Method attributes. Parent method attributes are visible when the method is not overridden. |
| `App\Controller\AdminController` | Class attributes and all parent class attributes. Child attributes precede inherited ones. |

Multiple attributes on the same target are combined with `AllOf`. Class attributes are not mixed into method lookup.

```php
use Componenta\Policy\Policies\OwnerPolicy;
use Componenta\Policy\Policies\PermissionPolicy;
use Componenta\Policy\Policies\RolePolicy;

#[RolePolicy('admin')]
abstract class BaseAdminController {}

final class PostController extends BaseAdminController
{
    #[PermissionPolicy(PostPermission::EDIT_ANY)]
    #[OwnerPolicy]
    public function update(int $id): void {}
}

$enforcer->check(PostController::class . '::update', $user, ['resource' => $post]);
$enforcer->check(PostController::class, $user);
```

The first call checks method policies only: `PostPermission::EDIT_ANY` and resource ownership. The second call checks the class-level `RolePolicy('admin')` inherited from `BaseAdminController`.

### CompositePolicyProvider

`CompositePolicyProvider` checks providers in order and returns the first found policy:

```php
$provider = new CompositePolicyProvider([$arrayProvider, $attributeProvider]);
```

`add()` appends a provider to the chain, and `prepend()` puts one at the front. These methods are intended for application bootstrap wiring; do not mutate the chain while requests are being handled.

### AllOfPolicyProvider

`AllOfPolicyProvider` also checks multiple providers, but it does not stop at the first found policy. It collects every policy found for one `actionId` and applies them through `AllOf`:

```php
use Componenta\Policy\Provider\AllOfPolicyProvider;

$provider = new AllOfPolicyProvider([$tenantProvider, $attributeProvider]);
```

Here `$attributeProvider` is an already created `AttributePolicyProvider` from the section above, and `$tenantProvider` is an application provider that returns a tenant-check policy for the same `actionId` values.

Behavior:

- returns `null` when no provider returns a policy;
- returns the single found policy as-is;
- returns `AllOf::of($policies)` when multiple policies are found.

Use this provider when rules from different sources must strengthen each other. For example: `AttributePolicyProvider` reads `#[PermissionPolicy]` from a command and an application provider adds a tenant check. For override scenarios, use `CompositePolicyProvider`.

`AllOfPolicyProvider` is not installed automatically by the default `PolicyProviderFactory`. To make it the final application provider, replace the `PolicyProviderInterface` service in the container or return this composite from your own factory.

### OneOfPolicyProvider

`OneOfPolicyProvider` checks multiple providers, collects every policy found for one `actionId`, and applies them through `OneOf`:

```php
use Componenta\Policy\Provider\OneOfPolicyProvider;

$provider = new OneOfPolicyProvider([$ownerProvider, $attributeProvider]);
```

Here `$attributeProvider` is an already created `AttributePolicyProvider` from the section above, and `$ownerProvider` is an application provider that returns an owner policy for the same `actionId` values.

Behavior:

- returns `null` when no provider returns a policy;
- returns the single found policy as-is;
- returns `OneOf::of($policies)` when multiple policies are found.

Use this provider when any rule from different sources is enough. For example: one provider allows the resource owner and `AttributePolicyProvider` reads `#[PermissionPolicy]` for a role with elevated permissions. Do not use it as a safe replacement for `AllOfPolicyProvider`: `OneOfPolicyProvider` broadens access.

`OneOfPolicyProvider` is not installed automatically by the default `PolicyProviderFactory` either. Register it explicitly as the final `PolicyProviderInterface` when this access-broadening behavior is an application requirement.

### CompiledPolicyProvider

`CompiledPolicyProvider` reads policy descriptors generated by `componenta/policy-app`. Use it in production when you do not want reflection-based attribute discovery on the hot path. By default, stale or invalid descriptors return `null`, and the next provider in the chain can handle the action. Set `ConfigKey::COMPILED_POLICIES_STRICT` to `true` to throw `InvalidCompiledPolicyException` instead, which is useful when broken generated cache should fail fast.

## Attributes

Policies whose constructors accept values allowed in PHP attributes can be used directly:

```php
use Componenta\Policy\ContainsMode;
use Componenta\Policy\Policies\PermissionPolicy;
use Componenta\Policy\Policies\RolePolicy;

#[RolePolicy('editor')]
#[PermissionPolicy([PostPermission::CREATE, PostPermission::EDIT_ANY], ContainsMode::ANY)]
final class PostController {}
```

When a policy needs container services, use `Componenta\Policy\Attribute\Policy`. The attribute stores the policy class and arguments, and `AttributePolicyProvider` creates the policy through `Componenta\DI\FactoryInterface`.

```php
use Componenta\Policy\Attribute\Policy;

#[Policy(PublishLimitPolicy::class, ['dailyLimit' => 10])]
public function store(): void {}
```

For cleaner syntax, create a domain attribute. The base `Policy` is `readonly`, so the subclass must also be `readonly`.

```php
use Componenta\Policy\Attribute\Policy;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
final readonly class PublishLimit extends Policy
{
    public function __construct(int $daily = 5)
    {
        parent::__construct(PublishLimitPolicy::class, ['dailyLimit' => $daily]);
    }
}

#[PublishLimit(daily: 10)]
public function store(): void {}
```

Composite attributes `Componenta\Policy\Attribute\AllOf` and `Componenta\Policy\Attribute\OneOf` accept direct policies and `Policy` references:

```php
use Componenta\Policy\Attribute\OneOf;
use Componenta\Policy\Attribute\Policy;
use Componenta\Policy\Policies\OwnerPolicy;
use Componenta\Policy\Policies\RolePolicy;

#[OneOf(
    new RolePolicy('admin'),
    new OwnerPolicy(),
    new Policy(PremiumAccessPolicy::class),
)]
public function edit(int $id): void {}
```

## Extension Points

| Replace | Contract | When to use |
|---|---|---|
| Policy source | `PolicyProviderInterface` | Policies are stored in a database, remote service, or another config system. |
| Context creation | `ContextFactoryInterface` | Context needs automatic attributes or another `ContextInterface` implementation. |
| Current actor for integrations | `ActorProviderInterface` | Middleware or another layer needs the current user without passing it to every call. |
| Policy | `PolicyInterface` | The application needs its own access rule. |
| Permission | `PermissionInterface` | The application has its own permission enum or entity. |
| Permission set | `PermissionCollectionInterface` | Permissions are not stored in the standard `PermissionCollection`. |

## Failures

| Case | Exception or result |
|---|---|
| A policy returns `DenyReason` | `check()` returns `AccessDenied`; `enforce()` throws `AccessDeniedException`. |
| No policy exists and `MissingPolicyBehavior::DENY` is active | Normal access denial. |
| No policy exists and `MissingPolicyBehavior::ALLOW` is active | Access is allowed. |
| Actor does not implement the interface required by the policy | `InvalidPolicyActorException`. |
| Required context attribute is missing | `MissingPolicyContextAttributeException`. |
| Context attribute has the wrong type | `InvalidPolicyContextAttributeException`. |
| Provider does not find a policy | Returns `null`; the next provider can continue lookup. |

## Related Packages

| Package | What to read |
|---|---|
| [`componenta/policy-app`](../policy-app/README.md) | Attribute discovery and compiled policy maps for cache. |
| [`componenta/cqrs`](../cqrs/README.md) | Using `PolicyMiddleware` for commands and queries. |
| [`componenta/di`](../di-container/README.md) | Creating policies through `FactoryInterface` and resolving services from the container. |
| [`componenta/identity`](../identity/README.md) | UUID identity for actors and owned resources. |
