<?php

declare(strict_types=1);

namespace Componenta\Policy;

use Componenta\Policy\Context\ContextFactory;
use Componenta\Policy\Context\ContextFactoryInterface;
use Componenta\Policy\Context\ContextInterface;
use Componenta\Policy\Exception\AccessDenied;
use Componenta\Policy\Exception\AccessDeniedException;
use Componenta\Policy\Exception\AccessDeniedInterface;
use Componenta\Policy\Exception\DenyReason;
use Componenta\Policy\Exception\PolicyExceptionInterface;

/**
 * Entry point for policy evaluation.
 *
 * Resolves a policy for an action via {@see PolicyProviderInterface}, prepares
 * the context, and delegates the authorization decision to the resolved policy.
 * Holds no authorization rules itself.
 *
 * Actor model:
 * - the enforcer accepts any object as actor;
 * - the actor is passed to the policy unchanged;
 * - if a concrete policy requires a more specific actor contract, it must
 *   validate the actor itself and throw {@see PolicyExceptionInterface} on
 *   invalid actor type.
 *
 * Result model:
 * - `true` means the action is allowed;
 * - {@see DenyReason} means the actor and context were valid, but the policy
 *   denied access by authorization rules;
 * - {@see PolicyExceptionInterface} means the policy could not be evaluated
 *   because of invalid actor, invalid/missing context data, misconfiguration,
 *   or another policy-layer error.
 *
 * Three flows are exposed:
 * - {@see check()} - detailed result without throwing on authorization denial;
 * - {@see enforce()} - throws {@see AccessDeniedException} on authorization denial;
 * - {@see can()} - boolean shortcut, discards denial details.
 *
 * Policy-layer exceptions are not converted to access denials. They are allowed
 * to bubble up because they represent invalid usage/configuration, not a valid
 * authorization decision.
 */
final readonly class PolicyEnforcer
{
    /**
     * Context attribute that overrides {@see MissingPolicyBehavior} for a single call.
     * Only values of type {@see MissingPolicyBehavior} are honoured; other values are ignored.
     */
    public const string ATTR_MISSING_POLICY_BEHAVIOR = '__MISSING_POLICY_BEHAVIOR';

    public function __construct(
        private PolicyProviderInterface $provider,
        private ContextFactoryInterface $factory = new ContextFactory,
        private MissingPolicyBehavior   $behavior = MissingPolicyBehavior::DENY,
    ) {}

    public function withProvider(PolicyProviderInterface $provider): self
    {
        return new self($provider, $this->factory, $this->behavior);
    }

    public function withFactory(ContextFactoryInterface $factory): self
    {
        return new self($this->provider, $factory, $this->behavior);
    }

    public function withBehavior(MissingPolicyBehavior $behavior): self
    {
        return new self($this->provider, $this->factory, $behavior);
    }

    /**
     * Evaluates whether the actor may perform the given action.
     *
     * This method does not throw on authorization denial. Instead, denied
     * decisions are returned as {@see AccessDeniedInterface}.
     *
     * It may still throw {@see PolicyExceptionInterface} if the resolved policy
     * cannot be evaluated because the actor, context, or policy configuration is
     * invalid.
     *
     * @param ContextInterface|array<string, mixed> $context Array is promoted through {@see ContextFactoryInterface}.
     *
     * @return true|AccessDeniedInterface `true` when allowed, otherwise a structured access denial.
     *
     * @throws PolicyExceptionInterface When policy evaluation fails due to invalid actor/context/configuration.
     */
    public function check(
        string $actionId,
        object $actor,
        ContextInterface|array $context = [],
    ): true|AccessDeniedInterface {
        [$context, $behavior] = $this->resolveContextAndBehavior($actionId, $context);
        $policy = $this->provider->provideFor($actionId);

        if ($policy === null) {
            return $this->handleMissingPolicy($actionId, $actor, $context, $behavior);
        }

        $result = $policy->enforce($actor, $context);

        if ($result === true) {
            return true;
        }

        return AccessDenied::fromReason($result, $actionId, $actor, $context);
    }

    /**
     * Enforces that the actor may perform the given action.
     *
     * Throws {@see AccessDeniedException} only for valid authorization denials:
     * the policy was resolved/evaluated and decided that access is not allowed,
     * or no policy was found and missing-policy behaviour is DENY.
     *
     * Policy-layer exceptions are not wrapped as access denials.
     *
     * @param ContextInterface|array<string, mixed> $context Array is promoted through {@see ContextFactoryInterface}.
     *
     * @throws AccessDeniedException When the resolved policy denies access or no policy is found under {@see MissingPolicyBehavior::DENY}.
     * @throws PolicyExceptionInterface When policy evaluation fails due to invalid actor/context/configuration.
     */
    public function enforce(
        string $actionId,
        object $actor,
        ContextInterface|array $context = [],
    ): void {
        $result = $this->check($actionId, $actor, $context);

        if ($result !== true) {
            throw AccessDeniedException::fromDenied($result);
        }
    }

    /**
     * Boolean shortcut over {@see check()}.
     *
     * Returns `true` only when the action is allowed. Any authorization denial is
     * collapsed to `false`.
     *
     * Policy-layer exceptions are not swallowed. Invalid actor/context errors
     * should remain visible because they are not authorization denials.
     *
     * @param ContextInterface|array<string, mixed> $context Array is promoted through {@see ContextFactoryInterface}.
     *
     * @throws PolicyExceptionInterface When policy evaluation fails due to invalid actor/context/configuration.
     */
    public function can(
        string $actionId,
        object $actor,
        ContextInterface|array $context = [],
    ): bool {
        return $this->check($actionId, $actor, $context) === true;
    }

    private function handleMissingPolicy(
        string $actionId,
        object $actor,
        ContextInterface $context,
        MissingPolicyBehavior $behavior,
    ): true|AccessDeniedInterface {
        if ($behavior === MissingPolicyBehavior::ALLOW) {
            return true;
        }

        return AccessDenied::fromReason(
            new DenyReason("No policy defined for action '$actionId'"),
            $actionId,
            $actor,
            $context,
        );
    }

    /**
     * Normalises the context into a {@see ContextInterface}, extracting and
     * stripping the per-call behaviour override if it is a valid enum value.
     *
     * Invalid override values are ignored intentionally: the override is an
     * optional per-call hint, not required policy input.
     *
     * @param ContextInterface|array<string, mixed> $context
     *
     * @return array{ContextInterface, MissingPolicyBehavior}
     */
    private function resolveContextAndBehavior(
        string $actionId,
        ContextInterface|array $context,
    ): array {
        $behavior = $this->behavior;

        if (is_array($context)) {
            if (isset($context[self::ATTR_MISSING_POLICY_BEHAVIOR]) || array_key_exists(self::ATTR_MISSING_POLICY_BEHAVIOR, $context)) {
                $override = $context[self::ATTR_MISSING_POLICY_BEHAVIOR];
                unset($context[self::ATTR_MISSING_POLICY_BEHAVIOR]);

                if ($override instanceof MissingPolicyBehavior) {
                    $behavior = $override;
                }
            }

            return [$this->factory->create($actionId, $context), $behavior];
        }

        if ($context->hasAttribute(self::ATTR_MISSING_POLICY_BEHAVIOR)) {
            $override = $context->getAttribute(self::ATTR_MISSING_POLICY_BEHAVIOR);
            $context = $context->withoutAttribute(self::ATTR_MISSING_POLICY_BEHAVIOR);

            if ($override instanceof MissingPolicyBehavior) {
                $behavior = $override;
            }
        }

        return [$context, $behavior];
    }
}