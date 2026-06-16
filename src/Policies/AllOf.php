<?php

declare(strict_types=1);

namespace Componenta\Policy\Policies;

use Componenta\Policy\Context\ContextInterface;
use Componenta\Policy\Exception\DenyReason;
use Componenta\Policy\PolicyInterface;

/**
 * AND-composition: passes only when every inner policy passes.
 * Short-circuits on the first denial and returns its reason. An empty set is vacuously true.
 */
final class AllOf extends AbstractPolicy
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
        foreach ($this->policies as $policy) {
            $result = $policy->enforce($actor, $context);

            if ($result !== true) {
                return $result;
            }
        }

        return true;
    }
}
