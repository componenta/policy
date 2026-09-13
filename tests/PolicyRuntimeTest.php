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

#[\Attribute(\Attribute::TARGET_CLASS)]
final class RuntimeNormalizedPolicy implements PolicyInterface
{
    public static int $constructions = 0;
    private readonly int $value;
    public function __construct(int $value)
    {
        ++self::$constructions;
        $this->value = $value * 2;
    }
    public function enforce(object $actor, ContextInterface $context): true|DenyReason
    {
        return $this->value === 4 ? true : new DenyReason('Unexpected normalized value', self::class);
    }
}

#[RuntimeNormalizedPolicy(2)]
final class RuntimeProtectedAction {}

it('uses native attributes and caches the resolved policy in every environment without loading legacy files', function (string $mode): void {
    $file = tempnam(sys_get_temp_dir(), 'policy-legacy-');
    file_put_contents($file, '<?php throw new \\RuntimeException("Legacy policy cache must not execute");');
    try {
        RuntimeNormalizedPolicy::$constructions = 0;
        $composition = (new ConfigFactory())->create(new Environment(['APP_ENV' => $mode]), new ConfigProvider(),
            static fn (): array => [ConfigKey::POLICY => ['compiled_policies_file' => $file]]);
        $provider = (new ContainerFactory())->create($composition->config, $composition->dependencies)->get(PolicyProviderInterface::class);
        $first = $provider->provideFor(RuntimeProtectedAction::class);
        expect($first?->enforce(new \stdClass(), new Context()))->toBeTrue()
            ->and($provider->provideFor(RuntimeProtectedAction::class))->toBe($first)
            ->and(RuntimeNormalizedPolicy::$constructions)->toBe(1);
    } finally {
        unlink($file);
    }
})->with(['development', 'production']);
