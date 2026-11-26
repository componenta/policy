<?php

declare(strict_types=1);

namespace Componenta\Policy\Policies;

use Attribute;
use Closure;
use Componenta\Policy\Context\ContextInterface;
use Componenta\Policy\Exception\CallbackPolicyException;
use Componenta\Policy\Exception\DenyReason;
use Componenta\Policy\Exception\PolicyExceptionInterface;
use Componenta\Policy\PolicyInterface;
use Throwable;

/** @template TActor of object */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class CallbackPolicy implements PolicyInterface
{
    /** @param Closure(TActor, ContextInterface): (true|DenyReason) $callback */
    public function __construct(private Closure $callback)
    {
    }

    /** @param TActor $actor */
    public function enforce(object $actor, ContextInterface $context): true|DenyReason
    {
        try {
            $result = ($this->callback)($actor, $context);
        } catch (PolicyExceptionInterface $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new CallbackPolicyException('Policy callback evaluation failed.', previous: $exception);
        }

        if ($result === true || $result instanceof DenyReason) {
            return $result;
        }

        throw new CallbackPolicyException(sprintf(
            'Policy callback must return true or %s, %s returned.',
            DenyReason::class,
            get_debug_type($result),
        ));
    }
}
