# Componenta Policy

`componenta/policy` is the authorization core for Componenta. Applications provide an action id, an actor object, and optional context; a `PolicyInterface` decides whether the action is allowed.

[Russian documentation](README.ru.md)

## Installation

```bash
composer require componenta/policy
```

The package requires PHP 8.5.13+ and integrates with Componenta Config 4, DI 6, Identity 1.1, and PSR-11 containers. For the development branch and its unreleased dependency revisions, see [development integration](docs/development.md).

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

`RoleInterface` exposes its stable name through the read-only `public string $name` property. `RoleCollection` and the built-in role policies use that property directly.

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

`ActorProviderInterface` resolves a subject from an integration-specific source such as an HTTP request, session, or authentication token. Its result has three distinct meanings:

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
- `enforce()` throws `AccessDeniedException` for a normal denial. Its details (including actor, context and internal reason) are for trusted application handling, not automatic serialization or disclosure to API clients.
- policy usage/configuration exceptions are not converted into denials.

Missing policies follow `MissingPolicyBehavior`. The default is `DENY`. A call may override the behavior through `PolicyEnforcer::ATTR_MISSING_POLICY_BEHAVIOR` in context. Set this override only in trusted application code. Do not forward client-controlled attribute bags or construct actors from unverified request data. Keep `DENY` as the default for protected actions.

## Built-in policies

| Policy | Rule |
|---|---|
| `Allow` | Always allows. |
| `Deny` | Always denies. |
| `CallbackPolicy` | Evaluates a closure against the current actor and context. |
| `PermissionPolicy` | Requires all or any configured permissions. |
| `RolePolicy` | Requires one of the configured role names. |
| `OwnerPolicy` | Requires an `IdentityInterface` actor to own the context resource. |
| `HierarchyPolicy` | Requires an actor role to outrank the target role(s). |
| `AllOf` | Every nested policy must allow. |
| `OneOf` | At least one nested policy must allow. |

For `PermissionPolicy`, `RolePolicy`, `OwnerPolicy`, and `HierarchyPolicy`, `Guest` is a valid subject that receives a normal denial. These policies still fail fast for unsupported non-guest actor objects. Context invariants are also fail-fast: for example, `OwnerPolicy` still requires a valid `resource` even when the actor is `Guest`.

`RolePolicy` and `HierarchyPolicy` accept `RoleInterface` and `RoleCollectionInterface` directly in addition to role-aware actor capabilities. When an actor exposes both role capabilities, both policies consider the single role and the role collection. An empty role collection is a valid role source and produces a normal denial when a role is required; it is not an invalid actor.

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

The default `Context` isolates array reference cells while preserving object and resource identity. To bound work on pathological copy-on-write arrays, it permits up to 100,000 visited entries per inspection or reference-copy stage, shared across all attributes in a construction or export. Inspection includes the top-level attribute entries. Repeated array occurrences count separately; already visited reference cells are not copied again. Exceeding a budget throws `ContextCapacityException` (a policy evaluation error), not an authorization denial. Pass large domain resources as objects rather than expanding them into context arrays.

Policies that require context validate it explicitly:

- missing mandatory attribute -> `MissingPolicyContextAttributeException`;
- wrong attribute type -> `InvalidPolicyContextAttributeException`.

`OwnerPolicy` reads `resource`; `HierarchyPolicy` reads `target`.

## Policy providers

`PolicyProviderInterface` resolves a policy by action id. The package includes:

- `ArrayPolicyProvider` — action-id map with lazy factories;
- `AttributePolicyProvider` — policies declared by PHP attributes;
- `CompositePolicyProvider` — first matching provider wins (later rules are not evaluated);
- `AllOfPolicyProvider` — combines policies found in multiple providers with `AllOf`;
- `OneOfPolicyProvider` — combines policies found in multiple providers with `OneOf`.

The default `PolicyProviderFactory` composes configured policy maps, custom providers, and native attributes in that order. Attribute policies are created on each `provideFor()` call using native attribute construction and the current DI factory. Configured policy factories are also invoked on each resolution. Explicitly registered policy objects retain their application-defined lifetime. Constructor and factory failures propagate on the invocation where they occur. Configuration-aware factories receive `Componenta\Config\ContainerValue`.

Disk policy compilation has been removed. Remove `compiled_policies`, `compiled_policies_file` and `compiled_policies_strict` settings, and remove `componenta/policy-app`. Register this package's ConfigProvider directly. Existing disk artifacts are unused; policy requires no application builder.

Configured provider class names and policy-map shapes are validated before use. Lazy configured policy factories must resolve to `PolicyInterface`.

## Attributes

Policies whose constructor arguments are valid PHP attribute values can be used directly:

```php
#[RolePolicy('admin')]
#[PermissionPolicy([PostPermission::CREATE], ContainsMode::ALL)]
final class PostController {}
```

When a policy needs DI services, use `Componenta\Policy\Attribute\Policy` and let `AttributePolicyProvider` create it through `Componenta\DI\FactoryInterface`.

`Componenta\Policy\Attribute\AllOf` and `Componenta\Policy\Attribute\OneOf` compose direct policies and policy references. An empty `AllOf` allows (vacuous truth); an empty `OneOf` denies. Do not use an empty `AllOf` as a placeholder for a rule that should restrict access. Use `Deny` explicitly.

Class-level attributes apply to class action IDs; method-level attributes apply to `Class::method` IDs. Method lookups do not automatically include class-level rules. Integrations requiring both levels must enforce both or explicitly combine the policies. The first-match provider chain can also intentionally replace an attribute policy with a configured policy; use `AllOfPolicyProvider` when all matching providers must constrain the action.

### Callback policies

Use `CallbackPolicy` for a small rule over the actor and context already available to the authorization layer:

```php
use Componenta\Policy\Context\ContextInterface;
use Componenta\Policy\Exception\DenyReason;
use Componenta\Policy\Policies\CallbackPolicy;
use Componenta\Policy\Policies\RolePolicy;

#[RolePolicy('editor')]
#[CallbackPolicy(static function (object $actor, ContextInterface $context): true|DenyReason {
    return $context->getAttribute('editingClosed', false) === true
        ? new DenyReason('Editing is closed.')
        : true;
})]
final class EditPost {}
```

A named callback can use native first-class callable syntax, including a private static method in the declaring class: `#[CallbackPolicy(self::decide(...))]`. PHP 8.5.13+ is required for correct class scope and visibility when a shared trait declares such a callable. In native attribute arguments, use `static function (...) { ... }` or a first-class callable; arrow functions and captured variables are not valid constant-expression closures.

The callback receives exactly `(object $actor, ContextInterface $context)` each time the policy is enforced. An actor-specific callback parameter is supported and inferred by static analysis; a mismatched actor produces a native `TypeError`, wrapped as a callback policy failure. Its result must be `true` or a `DenyReason`; a returned reason is preserved unchanged. `false`, `null`, and other values are contract errors. There is no implicit container argument or service injection. Use a policy class through `#[Policy(...)]` when the rule needs constructed dependencies.

`CallbackPolicyException` reports an invalid return or a foreign callback exception. Foreign exceptions, including native signature errors, remain available through `getPrevious()`. Existing exceptions implementing `PolicyExceptionInterface` propagate unchanged. `can()` does not hide evaluation failures, and `OneOf` does not turn them into an ordinary denial.

Construction stores the closure without running it. Neither decisions nor actor/context inputs are memoized. Repeated attributes retain the provider's existing AND semantics; `AllOf` and `OneOf` keep their usual short-circuit behavior. Runtime-created closures retain their normal PHP lifetime and any application-owned captured state.

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
- `ConfigKey::MISSING_POLICY_BEHAVIOR`.

## Integration boundaries

`componenta/policy` owns authorization semantics. Other packages own their own actor acquisition/transport concerns:

- `componenta/cqrs-policy` maps command/query actor semantics onto policy checks;
- `componenta/di` can inject the current user and build policy objects;
- `componenta/identity` provides UUID identity contracts.

Anonymous access is represented by `Guest`, not by `null`. `null` remains useful to integrations as an explicit signal that no actor has been resolved.
