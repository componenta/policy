<?php

declare(strict_types=1);

namespace Componenta\Policy\Tests;

use Componenta\Config\ConfigFactory;
use Componenta\Config\Environment;
use Componenta\DI\ContainerFactory;
use Componenta\DI\Exception\ResolutionException;
use Componenta\Policy\Attribute\Policy;
use Componenta\Policy\ConfigKey;
use Componenta\Policy\ConfigProvider;
use Componenta\Policy\Context\ContextInterface;
use Componenta\Policy\Exception\DenyReason;
use Componenta\Policy\PolicyEnforcer;
use Componenta\Policy\PolicyInterface;
use RuntimeException;
use stdClass;

final class PolicyConstructionProbe
{
    public int $attempts = 0;
    public bool $failFirst = true;
    public readonly RuntimeException $failure;

    public function __construct()
    {
        $this->failure = new RuntimeException('Policy dependency unavailable');
    }
}

final class ConstructionFailurePolicy implements PolicyInterface
{
    public function __construct(PolicyConstructionProbe $probe)
    {
        if (++$probe->attempts === 1 && $probe->failFirst) {
            throw $probe->failure;
        }
    }

    public function enforce(object $actor, ContextInterface $context): true|DenyReason
    {
        return true;
    }
}

#[Policy(ConstructionFailurePolicy::class)]
final class ConstructionProtectedAction
{
}

/** @param array<string, mixed> $policyConfig */
function failureProbeEnforcer(array $policyConfig, PolicyConstructionProbe $probe, ?\Closure $factory = null): PolicyEnforcer
{
    $composition = (new ConfigFactory())->create(
        new Environment([]),
        new ConfigProvider(),
        static fn (): array => [
            ConfigKey::POLICY => $policyConfig,
            \Componenta\Config\ConfigKey::DEPENDENCIES => [
                \Componenta\Config\ConfigKey::SERVICES => [
                    PolicyConstructionProbe::class => $probe,
                ],
                \Componenta\Config\ConfigKey::FACTORIES => $factory === null ? [] : [
                    ConstructionFailurePolicy::class => $factory,
                ],
            ],
        ],
    );
    $container = (new ContainerFactory())->create($composition->config, $composition->dependencies);

    return $container->get(PolicyEnforcer::class);
}

it('propagates policy construction failures without retrying or changing the exception', function (): void {
    $probe = new PolicyConstructionProbe();
    $enforcer = failureProbeEnforcer([], $probe);

    try {
        $enforcer->can(ConstructionProtectedAction::class, new stdClass());
        $this->fail('Expected the original policy construction failure.');
    } catch (ResolutionException $exception) {
        expect($exception->getPrevious())->toBe($probe->failure);
    }

    expect($probe->attempts)->toBe(1);
});

it('rejects a non-policy factory result without resolving it again through fallback', function (): void {
    $probe = new PolicyConstructionProbe();
    $enforcer = failureProbeEnforcer([], $probe, static function () use ($probe): stdClass {
        ++$probe->attempts;

        return new stdClass();
    });

    expect(fn (): bool => $enforcer->can(ConstructionProtectedAction::class, new stdClass()))
        ->toThrow(\UnexpectedValueException::class, 'Policy factory must return');
    expect($probe->attempts)->toBe(1);
});
