# Callback policies in native PHP attributes

**Readiness:** blocked by B1, review of the referenced conversation and its material decisions. This is a repository-grounded design draft, not an executable implementation instruction. The proposed target below must be reconciled with that conversation before production slices are derived.

## Objective

Allow a small authorization rule to be declared as a native PHP attribute or a directly constructed policy using a `Closure`, while preserving Componenta's distinction between permission granted, permission denied, and failure to evaluate a policy. Reuse the existing `PolicyInterface`, provider discovery, compositions, and enforcer instead of introducing another authorization pipeline.

## Scope

Add one callback-backed policy that is also a native attribute. Support standalone construction, method and class discovery, repeated attributes, and composition with existing policies. Document PHP 8.5 attribute expressions and test through public policy and provider/enforcer APIs.

This change does not migrate the package to DI 6, change the actor model, add a service locator, inject trailing callback arguments, add asynchronous rules, or replace named policies with service dependencies. It does not introduce policy/result compilation or authorization-result caching.

## Governing evidence

The inspected baseline is `componenta/policy` `main` at `dd21e6ea5d0a0e51b79999f5c299785455909065`. The only other discovered branch is the older `v2`. No `AGENTS.md`, package specification, or `docs/` specification directory was present in the inspected tree.

Relevant current sources:

- [PolicyInterface](https://github.com/componenta/policy/blob/dd21e6ea5d0a0e51b79999f5c299785455909065/src/PolicyInterface.php) defines `enforce(object, ContextInterface): true|DenyReason` and policy-layer failures.
- [PolicyEnforcer](https://github.com/componenta/policy/blob/dd21e6ea5d0a0e51b79999f5c299785455909065/src/PolicyEnforcer.php) maps denial to `AccessDenied`, `AccessDeniedException`, or `false` according to `check()`, `enforce()`, or `can()`; evaluation failures propagate.
- [AttributePolicyProvider](https://github.com/componenta/policy/blob/dd21e6ea5d0a0e51b79999f5c299785455909065/src/Provider/AttributePolicyProvider.php) already discovers attributes implementing `PolicyInterface`, combines repeated policies with AND, and creates native attribute instances on each resolution.
- [AllOf](https://github.com/componenta/policy/blob/dd21e6ea5d0a0e51b79999f5c299785455909065/src/Policies/AllOf.php) and [OneOf](https://github.com/componenta/policy/blob/dd21e6ea5d0a0e51b79999f5c299785455909065/src/Policies/OneOf.php) already own policy composition and short-circuit behavior.
- [PolicyRuntimeTest](https://github.com/componenta/policy/blob/dd21e6ea5d0a0e51b79999f5c299785455909065/tests/PolicyRuntimeTest.php) exercises native and factory policy resolution in development and production without legacy compiled-policy files.
- [composer.json](https://github.com/componenta/policy/blob/dd21e6ea5d0a0e51b79999f5c299785455909065/composer.json) currently requires PHP `^8.4`, Componenta DI `^5.0.3`, and uses Pest 4; `composer test` and `composer analyse` are established commands.

PHP 8.5 allows static anonymous functions and first-class callables in attribute arguments. Anonymous functions there must be `static`, cannot capture variables with `use`, and cannot be arrow functions. The implementation must preserve the native callable's lexical scope rather than reconstructing it as a public string method name. See the [closure constant-expression RFC](https://wiki.php.net/rfc/closures_in_const_expr) and [first-class callable manual](https://www.php.net/manual/en/functions.first_class_callable_syntax.php).

Syntax availability is distinct from the supported native first-class callable attribute contract. That contract requires PHP 8.5.13+ because earlier PHP 8.5 releases could resolve a static callable in a trait constant expression against the first using class and bypass visibility checks. The [PHP 8.5.13 changelog](https://www.php.net/ChangeLog-8.php#8.5.13) records the native fix. The package must not emulate or patch this engine behavior.

Repository source inspection establishes the baseline; no production implementation or test execution was performed for this specification-only work.

## Requirements and preservation constraints

- **R1 — Explicit decision:** a callback returns exactly `true` or a `DenyReason`. `false`, `null`, truthy strings/numbers, and arbitrary objects are invalid results, not alternate denial syntax.
- **R2 — Explicit inputs:** the callback receives the actor and `ContextInterface` supplied to `enforce()`. The package does not infer a current actor, request, container, or tenant from global state.
- **R3 — Existing composition:** callback policies work wherever `PolicyInterface` instances or native policy attributes already work, including `AllOf`/`OneOf` and provider/enforcer entry points.
- **R4 — Error separation:** a failure to evaluate the rule remains a policy-layer failure. It never becomes `DenyReason`, a missing policy, `false` from `can()`, or permission granted by another alternative.
- **P1 — Enforcer behavior:** the three existing outcomes and `check()`/`enforce()`/`can()` mappings remain unchanged.
- **P2 — Discovery behavior:** method/class lookup, class inheritance, repeated-attribute AND composition, explicit DI policy references, and composite short-circuit semantics remain unchanged.
- **P3 — Request independence:** providers continue creating native attribute instances for each resolution. Neither callbacks' returned decisions nor actor/context arguments are retained in a package-level result cache.

## Desired-state architecture

### Ownership and dependency direction

`Componenta\Policy\Policies\CallbackPolicy` owns adapting a callback to `PolicyInterface`. It also carries native PHP attribute metadata, following the existing directly usable policy-attribute pattern demonstrated by `Policies\Allow` and other policy implementations.

It depends on `Closure`, `PolicyInterface`, `ContextInterface`, `DenyReason`, and policy-layer exceptions. It has no container, invoker, request, session, database, or identity-service dependency. Existing providers discover it through `PolicyInterface`; existing compositions and the enforcer remain the execution owners at their current boundaries.

One new concrete `CallbackPolicyException` under the existing policy exception hierarchy represents callback contract or execution failures. A callback interface, registry, compiler, callback resolver, and additional provider are unnecessary.

### Proposed public contract

```php
namespace Componenta\Policy\Policies;

#[\Attribute(
    \Attribute::TARGET_CLASS
    | \Attribute::TARGET_METHOD
    | \Attribute::IS_REPEATABLE,
)]
final readonly class CallbackPolicy implements \Componenta\Policy\PolicyInterface
{
    /**
     * @param \Closure(object, \Componenta\Policy\Context\ContextInterface):
     *     (true|\Componenta\Policy\Exception\DenyReason) $callback
     */
    public function __construct(\Closure $callback);

    public function enforce(
        object $actor,
        \Componenta\Policy\Context\ContextInterface $context,
    ): true|\Componenta\Policy\Exception\DenyReason;
}
```

This is a contract sketch, not executable class code. The callback is stored privately. The constructor accepts `Closure`, including a first-class callable. String and array callable specifications are not new accepted input forms; callers can explicitly create a first-class callable before normal runtime construction.

`CallbackPolicyException` is a final subclass of the existing `Componenta\Policy\Exception\PolicyException`. It therefore implements `PolicyExceptionInterface`. No second denial-result type is introduced.

### Invocation and result semantics

1. Construction stores the supplied callback; construction/discovery does not evaluate its authorization rule.
2. Each `enforce($actor, $context)` invokes the callback once with these two arguments and in this order. The package supplies no trailing arguments and performs no DI lookup.
3. The actor and context retain their supplied object identity. The package does not deep-copy, mutate, replace, or rebind them. Application callbacks remain responsible for preserving the authorization rule's own invariants.
4. A result identical to `true` is returned as `true`.
5. A `DenyReason` is returned unchanged, including its message, provenance, and object identity. An empty `policyClass` is not rewritten by the adapter.
6. Any other returned value raises `CallbackPolicyException`. The error identifies an invalid callback result by type; it does not include the actor, context contents, or returned value.
7. An exception implementing `PolicyExceptionInterface` raised by the callback propagates unchanged.
8. Any other `Throwable` raised during callback invocation is wrapped once in `CallbackPolicyException`, preserving it as `previous`. Its message identifies policy callback execution failure without rendering actor/context values.
9. Invalid constructor arguments and native attribute-construction errors retain native construction-failure behavior; they are not interpreted as authorization decisions.

The callback must validate any actor capabilities and required context data it relies on. Existing `InvalidPolicyActorException`, `MissingPolicyContextAttributeException`, and `InvalidPolicyContextAttributeException` remain the appropriate domain-specific errors. A missing or invalid required input cannot be converted to denial merely to avoid raising an exception.

There is no callback cloning or PHP closure-lifetime emulation. Directly supplied runtime closures retain PHP's native scope and state behavior. The package invokes the current callback on every evaluation and does not cache its result.

### Composition and discovery

No special-case branch for the new class is required in `AttributePolicyProvider`: it is discoverable through the existing `PolicyInterface` filter. Class and method discovery must retain existing precedence and inheritance behavior.

Repeated callback attributes participate in the existing AND composition. A callback returning `DenyReason` short-circuits an AND composition. A callback returning `true` short-circuits an OR composition. Callback evaluation errors terminate composition by propagation; OR must not treat an error as an ordinary failed alternative.

Callback policies can also be supplied to `Policies\AllOf` and `Policies\OneOf` or wrapped in `Attribute\AllOf`/`Attribute\OneOf`. Existing DI-based named policy references continue serving dependency-bearing rules.

### Proposed usage

The following example illustrates the new API after implementation. All inline attribute callbacks use PHP 8.5-compatible static anonymous functions.

```php
use Componenta\Policy\Context\ContextInterface;
use Componenta\Policy\Exception\DenyReason;
use Componenta\Policy\Exception\InvalidPolicyContextAttributeException;
use Componenta\Policy\Exception\MissingPolicyContextAttributeException;
use Componenta\Policy\Policies\CallbackPolicy;

#[CallbackPolicy(
    static function (object $actor, ContextInterface $context): true|DenyReason {
        if (!$context->hasAttribute('editingEnabled')) {
            throw new MissingPolicyContextAttributeException('editingEnabled', 'bool');
        }

        $enabled = $context->getAttribute('editingEnabled');
        if (!is_bool($enabled)) {
            throw InvalidPolicyContextAttributeException::expected(
                'editingEnabled',
                $enabled,
                'bool',
            );
        }

        return $enabled ? true : new DenyReason('Editing is disabled.');
    },
)]
final class EditDocument {}
```

An equivalent declaration can use `#[CallbackPolicy(self::decide(...))]` inside the declaring class when `decide` is a compatible static method. This conversion must retain PHP's normal lexical visibility, including a valid private static method reference.

## Compatibility and runtime

This proposed capability is additive: no current `PolicyInterface`, provider, enforcer, composition, or named policy API is removed or widened. Existing DI dependencies are not changed solely to support a directly invoked closure.

Inline callback and first-class callable attribute syntax became available in PHP 8.5, while the supported native first-class callable attribute contract in this draft requires PHP 8.5.13+. The reviewed package currently advertises PHP `^8.4`; B1 must settle whether this feature changes the package-wide minimum or retains PHP 8.4 for the existing/runtime-construction API with separately gated PHP 8.5.13+ native-attribute coverage. Do not silently choose that release-policy change during implementation. Composer constraints, CI, examples, and fixture loading must distinguish the retained package/runtime-construction surface, if any, from the supported native attribute runtime; PHP 8.5.0–8.5.12 must not be advertised as satisfying the full native callable scope/visibility contract.

No persisted policy result or compiled metadata migration is introduced. The existing native per-resolution policy construction remains authoritative.

## Verification strategy

Use TDD through public contracts. First demonstrate each new capability is absent or fails at the baseline; then implement the smallest corresponding behavior. Keep fixture callbacks and assertions independent of internal helper names. A test must distinguish authorization denial from inability to evaluate.

| Requirement | Public proof |
|---|---|
| R1, R2 | A directly constructed policy receives the same actor/context, permits on `true`, and returns an exact supplied `DenyReason`; callbacks returning `false`, `null`, a truthy scalar, or another object raise `CallbackPolicyException`. |
| R4, P1 | Run a callback error through `PolicyEnforcer::check()`, `enforce()`, and `can()`; all preserve policy-layer failure. Ordinary denial retains the three existing mappings. |
| R4 | An existing `PolicyExceptionInterface` instance is propagated unchanged; a foreign exception is wrapped with the exact original cause. No failure becomes a denial. |
| R3, P2 | Resolve class and method callback attributes through `AttributePolicyProvider`, including a real static anonymous function and a private static first-class callable. Apply the resulting policy through the enforcer. |
| R3, P2 | Repeated callback attributes preserve AND behavior; nested `AllOf`/`OneOf` mixes callback and existing policies, including short-circuiting and error propagation. |
| P2 | Keep the current provider inheritance tests and named DI policy-reference test as preservation controls. No new provider registration should be required. |
| P3 | Reuse one provider/enforcer for successive actors and contexts with different expected decisions; re-resolve native callback policies in development and production and prove decisions do not bleed between calls. |
| Language/scope contract | Parse and execute native callback fixtures on the supported PHP 8.5.13+ runtime line. A shared trait declaring a first-class callable in a constant expression is used by two classes with their own private static decision methods; repeatedly resolve and evaluate both classes' attributes and prove each retains its own scope and decision. Native invalid private access must remain rejected. Include normal native failure of an invalid attribute argument. |

The direct-adapter cases belong beside the existing policy unit tests; discovery cases belong with `tests/Provider/AttributePolicyProviderTest.php`; environment and repeated-resolution cases extend the behavior covered by `tests/PolicyRuntimeTest.php`. Existing enforcer and composite tests provide preservation baselines.

After focused tests pass, use the existing `composer test` and `composer analyse` commands. Do not migrate unrelated tests, add a second test framework, or prescribe a performance threshold without a measured requirement. Verify that no new DI/container call or decision cache has been introduced.

## Technical change surface

Proposed new symbols have intentionally specified locations within verified existing namespaces:

- Add `src/Policies/CallbackPolicy.php` — the callback adapter and native attribute.
- Add `src/Exception/CallbackPolicyException.php` — policy-layer callback execution/result failure.
- Add focused callback-policy tests and fixtures within the current `tests/Policies` and `tests/Fixture/AttributeTargets` structures.
- Extend `tests/Provider/AttributePolicyProviderTest.php`, applicable enforcer/composite tests, and `tests/PolicyRuntimeTest.php` only for the stated public behaviors.
- Update `README.md` and `README.ru.md` with equivalent API and error/lifecycle semantics; update runtime metadata and CI only according to the settled B1 decision.

No production change is expected in `PolicyInterface`, `PolicyEnforcer`, `AttributePolicyProvider`, `AllOf`, `OneOf`, their factories, or config registration. If implementation reveals such a change is required, first explain the missing boundary in this specification rather than adding another callback path.

## Blocking evidence and implementation sequencing

### B1 — Referenced conversation and runtime/API decisions

The required linked design discussion was unavailable during this specification-only review. It may specify callback invocation/DI rules, package compatibility, naming, or readiness constraints that materially alter the proposed target. Its decisions must be reconciled with this document before implementation readiness can be claimed.

The bounded evidence task is to retrieve the relevant conversation text or explicit decisions, compare them with this draft, and settle the invocation contract and runtime policy. No production changes or behavioral spike are needed. Stop when each relevant decision has an identified disposition and the specification can state a single target without contradicting the prior request.

Do not execute production slices while B1 remains. After reconciliation, derive slices in this dependency order: direct callback outcome/error adapter; native attribute/provider/enforcer integration and lifecycle proof; documentation/runtime consistency and final convergence. Each slice must reference the settled contracts above and establish its public failing signal before implementation.

## Convergence check

Before declaring a future implementation complete, compare it with R1–R4 and P1–P3. Reject drift that adds bool-denial shorthand, hidden dependency resolution, swallowed errors, callback/result caching, a second policy pipeline, or changed policy composition. Confirm all examples parse on the stated minimum version and no unsupported arrow function is used in an attribute argument.
