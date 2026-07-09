<?php

declare(strict_types=1);

use Componenta\Config\Config;
use Componenta\DI\FactoryInterface;
use Componenta\Policy\ConfigKey;
use Componenta\Policy\Context\Context;
use Componenta\Policy\Exception\InvalidCompiledPolicyException;
use Componenta\Policy\Policies\Allow;
use Componenta\Policy\PolicyProviderFactory;
use Componenta\Policy\Provider\AttributePolicyProvider;
use Componenta\Policy\Tests\Fixture\FakeActor;
use Componenta\Policy\Tests\Fixture\FakeContainer;
use Componenta\Policy\Tests\Fixture\FakeFactory;
use Componenta\Policy\Tests\Fixture\FakeRole;

describe('PolicyProviderFactory', function () {
    it('does not require app path resolver when compiled policy cache is not configured', function () {
        $provider = (new PolicyProviderFactory())(new FakeContainer([
            'config' => new Config([ConfigKey::POLICY => []]),
            FactoryInterface::class => new FakeFactory(),
        ]));

        expect($provider)->toBeInstanceOf(AttributePolicyProvider::class);
    });

    it('loads compiled policies from the configured cache file path', function () {
        $file = tempnam(sys_get_temp_dir(), 'componenta-policy-cache-');
        if ($file === false) {
            throw new RuntimeException('Unable to create temp file.');
        }

        file_put_contents($file, '<?php return ' . var_export([
            'version' => ConfigKey::CACHE_VERSION,
            'map' => [
                'posts.create' => [
                    'kind' => 'direct',
                    'class' => Allow::class,
                    'arguments' => [],
                ],
            ],
        ], true) . ';');

        try {
            $provider = (new PolicyProviderFactory())(new FakeContainer([
                'config' => new Config([
                    ConfigKey::POLICY => [
                        ConfigKey::COMPILED_POLICIES_FILE => $file,
                    ],
                ]),
                FactoryInterface::class => new FakeFactory(),
            ]));

            $policy = $provider->provideFor('posts.create');

            expect($policy?->enforce(new FakeActor(1, new FakeRole('admin')), new Context()))
                ->toBeTrue();
        } finally {
            unlink($file);
        }
    });

    it('passes strict compiled policy mode to the compiled provider', function () {
        $provider = (new PolicyProviderFactory())(new FakeContainer([
            'config' => new Config([
                ConfigKey::POLICY => [
                    ConfigKey::COMPILED_POLICIES => [
                        'broken' => [
                            'kind' => 'direct',
                            'class' => 'MissingPolicy',
                            'arguments' => [],
                        ],
                    ],
                    ConfigKey::COMPILED_POLICIES_STRICT => true,
                ],
            ]),
            FactoryInterface::class => new FakeFactory(),
        ]));

        expect(fn () => $provider->provideFor('broken'))
            ->toThrow(InvalidCompiledPolicyException::class, 'broken');
    });
});
