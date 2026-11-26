<?php
declare(strict_types=1);
namespace Componenta\Policy\Tests;

use Componenta\Config\ConfigFactory;
use Componenta\Config\Environment;
use Componenta\DI\ContainerFactory;
use Componenta\Policy\ConfigKey;
use Componenta\Policy\ConfigProvider;
use Componenta\Policy\Context\Context;
use Componenta\Policy\Context\ContextInterface;
use Componenta\Policy\Exception\DenyReason;
use Componenta\Policy\PolicyInterface;
use Componenta\Policy\PolicyProviderInterface;
use Componenta\Policy\PolicyEnforcer;
use Componenta\Policy\Policies\CallbackPolicy;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class RuntimeNormalizedPolicy implements PolicyInterface
{
    public static int $constructions = 0;
    private readonly int $value;
    private int $checks = 0;
    public function __construct(int $value)
    {
        ++self::$constructions;
        $this->value = $value * 2;
    }
    public function enforce(object $actor, ContextInterface $context): true|DenyReason
    {
        return $this->value === 4 && ++$this->checks === 1 ? true : new DenyReason('Policy instance was reused', self::class);
    }
}

#[RuntimeNormalizedPolicy(2)]
final class RuntimeProtectedAction {}

#[\Componenta\Policy\Attribute\Policy(RuntimeNormalizedPolicy::class, ['value' => 2])]
final class RuntimeFactoryProtectedAction {}

it('creates attribute policies for each resolution in every environment without loading legacy files', function (string $mode, string $action): void {
    $file = tempnam(sys_get_temp_dir(), 'policy-legacy-');
    file_put_contents($file, '<?php throw new \\RuntimeException("Legacy policy cache must not execute");');
    try {
        RuntimeNormalizedPolicy::$constructions = 0;
        $composition = (new ConfigFactory())->create(new Environment(['APP_ENV' => $mode]), new ConfigProvider(),
            static fn (): array => [ConfigKey::POLICY => ['compiled_policies_file' => $file]]);
        $provider = (new ContainerFactory())->create($composition->config, $composition->dependencies)->get(PolicyProviderInterface::class);
        $first = $provider->provideFor($action);
        expect($first?->enforce(new \stdClass(), new Context()))->toBeTrue()
            ->and($provider->provideFor($action)?->enforce(new \stdClass(), new Context()))->toBeTrue()
            ->and(RuntimeNormalizedPolicy::$constructions)->toBe(2);
    } finally {
        unlink($file);
    }
})->with(['development', 'production'])->with([RuntimeProtectedAction::class, RuntimeFactoryProtectedAction::class]);

#[CallbackPolicy(static function (object $actor, ContextInterface $context): true|DenyReason {
    return $context->getAttribute('expectedActor') === $actor
        ? true
        : new DenyReason('The actor does not match this evaluation.');
})]
final class RuntimeCallbackProtectedAction {}

it('evaluates native callbacks for current actors and contexts through the configured runtime', function (string $mode): void {
    $composition = (new ConfigFactory())->create(new Environment(['APP_ENV' => $mode]), new ConfigProvider());
    $enforcer = (new ContainerFactory())->create($composition->config, $composition->dependencies)->get(PolicyEnforcer::class);
    $first = new \stdClass();
    $second = new \stdClass();

    expect($enforcer->can(RuntimeCallbackProtectedAction::class, $first, ['expectedActor' => $first]))->toBeTrue()
        ->and($enforcer->can(RuntimeCallbackProtectedAction::class, $second, ['expectedActor' => $first]))->toBeFalse()
        ->and($enforcer->can(RuntimeCallbackProtectedAction::class, $second, ['expectedActor' => $second]))->toBeTrue()
        ->and($enforcer->can(RuntimeCallbackProtectedAction::class, $first, ['expectedActor' => $second]))->toBeFalse();
})->with(['development', 'production']);
