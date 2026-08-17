# Componenta Policy

`componenta/policy` is the authorization core for Componenta. Applications provide an action id, an actor object, and optional context; a `PolicyInterface` decides whether the action is allowed.

[Russian documentation](README.ru.md)

## Installation

```bash
composer require componenta/policy
```

The package targets PHP 8.4+ and integrates with `componenta/config`, `componenta/di`, `componenta/identity`, and PSR-11 containers.

## Core model

```php
interface PolicyInterface
{
    public function enforce(
        object $actor,
        ContextInterface $context,
    ): true|DenyReason;
}
```

A policy returns `true` when access is allowed and `DenyReason` when the rule is validly evaluated but access is denied. Invalid actor capabilities, missing required context, or malformed configuration are policy errors and remain exceptions.

There is no universal actor composite interface. `PolicyEnforcer` accepts `object`; each policy checks only the capabilities it needs, for example:

- `IdentityInterface` for identity/ownership;
- `PermissionAwareInterface` for direct permissions;
- `RoleAwareInterface` for one role;
- `RoleCollectionAwareInterface` for a role collection carried by an actor;
- `RoleInterface` or `RoleCollectionInterface` directly where role policies accept role objects themselves;
- application-specific capability interfaces for custom policies.

`RoleInterface` exposes its stable name through the read-only `public string $name` property. `RoleCollection` and the built-in role policies use that property directly; no legacy `getName()` role contract exists.

## Actor semantics

Two integration contracts describe where an actor comes from:

```php
interface ActorAwareInterface
{
    public object $actor { get; }
}

interface ActorProviderInterface
{
    public function getActor(): ?object;
}
```

`ActorAwareInterface` is for messages and other objects that explicitly carry the policy subject.

`ActorProviderInterface` resolves a subject from an integration-specific environment such as an HTTP request, session, token, or worker context. Its result has three distinct meanings:

```text
concrete object -> resolved actor
Guest           -> explicit anonymous actor
null            -> no actor could be resolved
```

The policy layer never converts `null` to `Guest`. An integration that wants anonymous access represented as a subject returns `Guest` explicitly. The package default `GuestActorProvider` does exactly that.

`Guest` is a valid anonymous actor. Built-in protected policies deny `Guest` normally. An unrelated object that lacks the capability required by a policy remains an integration/configuration error and causes `InvalidPolicyActorException`.

## PolicyEnforcer

`PolicyEnforcer` resolves a policy through `PolicyProviderInterface`, creates/normalizes context, and evaluates the policy.

```php
$result = $enforcer->check('posts.update', $user, [
    'resource' => $post,
]);

$allowed = $enforcer->can('posts.update', $user, [
    'resource' => $post,
]);

$enforcer->enforce('posts.update', $user, [
    'resource' => $post,
]);
```

- `check()` returns `true|AccessDenied` for normal authorization outcomes.
- `can()` returns a boolean for normal outcomes.
- `enforce()` throws `AccessDeniedException` for a normal denial.
- policy usage/configuration exceptions are not converted into denials.

Missing policies follow `MissingPolicyBehavior`. The default is `DENY`. A call may override the behavior through `PolicyEnforcer::ATTR_MISSING_POLICY_BEHAVIOR` in context.

## Built-in policies

| Policy | Rule |
|---|---|
| `Allow` | Always allows. |
| `Deny` | Always denies. |
| `PermissionPolicy` | Requires all or any configured permissions. |
| `RolePolicy` | Requires one of the configured role names. |
| `OwnerPolicy` | Requires an `IdentityInterface` actor to own the context resource. |
| `HierarchyPolicy` | Requires an actor role to outrank the target role(s). |
| `AllOf` | Every nested policy must allow. |
| `OneOf` | At least one nested policy must allow. |

For `PermissionPolicy`, `RolePolicy`, `OwnerPolicy`, and `HierarchyPolicy`, `Guest` is a valid subject that receives a normal denial. These policies still fail fast for unsupported non-guest actor objects. Context invariants are also fail-fast: for example, `OwnerPolicy` still requires a valid `resource` even when the actor is `Guest`.

`RolePolicy` and `HierarchyPolicy` accept `RoleInterface` and `RoleCollectionInterface` directly in addition to role-aware actor capabilities. An empty role collection is a valid role source and produces a normal denial when a role is required; it is not an invalid actor.

### Permission example

```php
use Componenta\Policy\Actor\PermissionAwareInterface;
use Componenta\Policy\Permission\PermissionCollection;
use Componenta\Policy\Permission\PermissionCollectionInterface;
use Componenta\Policy\Permission\PermissionInterface;
use Componenta\Policy\Policies\PermissionPolicy;

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
$policy = new PermissionPolicy(PostPermission::CREATE);
```

`PermissionPolicy` merges permissions exposed directly, through one role, through `RoleCollectionAwareInterface`, or through a direct `RoleCollectionInterface`. An empty role collection is still a valid permission source and therefore yields a normal missing-permission denial rather than `InvalidPolicyActorException`.

## Context

`ContextInterface` is an immutable attribute bag for one policy evaluation. Arrays passed to `PolicyEnforcer` are converted through `ContextFactoryInterface`.

Policies that require context validate it explicitly:

- missing mandatory attribute -> `MissingPolicyContextAttributeException`;
- wrong attribute type -> `InvalidPolicyContextAttributeException`.

`OwnerPolicy` reads `resource`; `HierarchyPolicy` reads `target`.

## Policy providers

`PolicyProviderInterface` resolves a policy by action id. The package includes:

- `ArrayPolicyProvider` — action-id map with lazy factories;
- `AttributePolicyProvider` — policies declared by PHP attributes;
- `CompositePolicyProvider` — first matching provider wins;
- `AllOfPolicyProvider` — combines policies found in multiple providers with `AllOf`;
- `OneOfPolicyProvider` — combines policies found in multiple providers with `OneOf`;
- `CompiledPolicyProvider` — consumes descriptors produced by `componenta/policy-app`.

The default `PolicyProviderFactory` composes configured policy maps, custom providers, compiled policy descriptors, and attribute fallback. Configuration-aware DI factories receive `Componenta\Config\ContainerValue`; they use its typed service access and its `Config` value instead of treating configuration as an untyped container entry.

Configured provider class names and policy-map shapes are validated before use. Lazy configured policy factories must resolve to `PolicyInterface`.

## Attributes

Policies whose constructor arguments are valid PHP attribute values can be used directly:

```php
#[RolePolicy('admin')]
#[PermissionPolicy([PostPermission::CREATE], ContainsMode::ALL)]
final class PostController {}
```

When a policy needs DI services, use `Componenta\Policy\Attribute\Policy` and let `AttributePolicyProvider` create it through `Componenta\DI\FactoryInterface`.

`Componenta\Policy\Attribute\AllOf` and `Componenta\Policy\Attribute\OneOf` compose direct policies and policy references.

## Action ids

`PolicyEnforcer` receives a string action id explicitly. Integrations may use `ActionIdAwareInterface` to let a subject expose a stable action id:

```php
interface ActionIdAwareInterface
{
    public string $actionId { get; }
}
```

For example, the CQRS integration uses this property when present and otherwise falls back to the message class name.

## Configuration

Register:

```php
return [
    new Componenta\Policy\ConfigProvider(),
];
```

The provider registers `PolicyEnforcer`, `PolicyProviderInterface`, `ContextFactoryInterface`, and a default `ActorProviderInterface` that returns `Guest`.

Relevant configuration keys include:

- `ConfigKey::POLICY`;
- `ConfigKey::POLICIES`;
- `ConfigKey::PROVIDERS`;
- `ConfigKey::MISSING_POLICY_BEHAVIOR`;
- `ConfigKey::COMPILED_POLICIES`;
- `ConfigKey::COMPILED_POLICIES_FILE`;
- `ConfigKey::COMPILED_POLICIES_STRICT`.

## Integration boundaries

`componenta/policy` owns authorization semantics. Other packages own their own actor acquisition/transport concerns:

- `componenta/policy-app` discovers and compiles attribute policies;
- `componenta/cqrs-policy` maps command/query actor semantics onto policy checks;
- `componenta/di` can inject the current user and build policy objects;
- `componenta/identity` provides UUID identity contracts.

Anonymous access is represented by `Guest`, not by `null`. `null` remains useful to integrations as an explicit signal that no actor has been resolved.
