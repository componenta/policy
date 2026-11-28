<?php

declare(strict_types=1);

namespace Componenta\Policy\Internal;

use Componenta\Config\Config;
use Componenta\Policy\ConfigKey;
use InvalidArgumentException;

/** @internal Reads the policy configuration without implicit type conversion. */
final class PolicyConfiguration
{
    /** @return array<array-key, mixed> */
    public static function read(Config $config): array
    {
        $section = $config->get(ConfigKey::POLICY, []);

        if (!is_array($section)) {
            throw new InvalidArgumentException(sprintf(
                'Policy configuration "%s" must be an array; %s given.',
                ConfigKey::POLICY,
                get_debug_type($section),
            ));
        }

        return $section;
    }

    /** @param array<array-key, mixed> $section */
    public static function value(array $section, string $key, mixed $default): mixed
    {
        return array_key_exists($key, $section) ? $section[$key] : $default;
    }
}
