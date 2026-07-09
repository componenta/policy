<?php

declare(strict_types=1);

namespace Componenta\Policy;

use Componenta\Policy\Context\ContextInterface;
use Componenta\Policy\Exception\DenyReason;
use Componenta\Policy\Exception\PolicyExceptionInterface;

/**
 * Authorization rule that evaluates an actor against a given context.
 *
 * Contract:
 * - returns `true` when the actor is authorized;
 * - returns {@see DenyReason} when the actor is valid and the context is valid,
 *   but access is denied by authorization rules;
 * - throws {@see PolicyExceptionInterface} when the policy cannot be evaluated
 *   because of invalid actor, invalid/missing context data, misconfiguration,
 *   or another policy-layer infrastructure error.
 *
 * A policy MUST NOT return {@see DenyReason} for invalid input/context errors.
 * Deny reasons are only for authorization decisions.
 */
interface PolicyInterface
{
    /**
     * @throws PolicyExceptionInterface
     */
    public function enforce(object $actor, ContextInterface $context): true|DenyReason;
}