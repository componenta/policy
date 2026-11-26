<?php

declare(strict_types=1);

namespace Componenta\Policy\Tests\StaticAnalysis;

use Componenta\Policy\Context\ContextInterface;
use Componenta\Policy\Exception\DenyReason;
use Componenta\Policy\Policies\CallbackPolicy;

final readonly class TypedActor
{
    public function __construct(public bool $active) {}
}

final class TypedActorRules
{
    public static function decide(TypedActor $actor, ContextInterface $context): true|DenyReason
    {
        return $actor->active ? true : new DenyReason('Inactive actor.');
    }
}

#[CallbackPolicy(TypedActorRules::decide(...))]
final class TypedActorAction {}

function evaluateTypedPolicy(TypedActor $actor, ContextInterface $context): true|DenyReason
{
    $policy = new CallbackPolicy(TypedActorRules::decide(...));

    return $policy->enforce($actor, $context);
}
