<?php

declare(strict_types=1);

namespace Componenta\Policy\Policies;

use Componenta\Policy\Context\ContextInterface;
use Componenta\Policy\Exception\DenyReason;
use Componenta\Policy\PolicyInterface;

/**
 * OR-composition: passes when any inner policy passes.
 * Short-circuits on the first success. When every policy denies, returns the
 * last denial reason. An empty set denies.
 */
final class OneOf extends AbstractPolicy
{
    /** @var PolicyInterface[] */
    private array $policies;

    public function __construct(PolicyInterface ...$policies)
    {
        $this->policies = $policies;
    }

    /**
     * @param iterable<PolicyInterface> $policies
     */
    public static function of(iterable $policies): self
    {
        return new self(...$policies);
    }

    public function enforce(object $actor, ContextInterface $context): true|DenyReason
    {
        $lastDenied = null;

        foreach ($this->policies as $policy) {
            $result = $policy->enforce($actor, $context);

            if ($result === true) {
                return true;
            }

            $lastDenied = $result;
        }

        return $lastDenied ?? $this->deny('None of the policies allowed access');
    }
}
