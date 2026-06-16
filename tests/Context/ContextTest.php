<?php

declare(strict_types=1);

use Componenta\Policy\Context\Context;

it('returns the default when an attribute is missing', function () {
    $context = new Context(['a' => 1]);

    expect($context->getAttribute('missing', 'fallback'))->toBe('fallback')
        ->and($context->getAttribute('missing'))->toBeNull();
});

it('distinguishes a null-valued attribute from a missing one', function () {
    $context = new Context(['a' => null]);

    expect($context->hasAttribute('a'))->toBeTrue()
        ->and($context->hasAttribute('b'))->toBeFalse();
});

it('returns all attributes as a plain associative array via getAttributes()', function () {
    $context = new Context(['a' => 1, 'b' => 'two']);

    expect($context->getAttributes())->toBe(['a' => 1, 'b' => 'two']);
});

describe('immutability', function () {
    it('withAttribute returns a new instance and leaves the original untouched', function () {
        $original = new Context(['a' => 1]);

        $modified = $original->withAttribute('b', 2);

        expect($original->hasAttribute('b'))->toBeFalse()
            ->and($modified->getAttribute('b'))->toBe(2)
            ->and($modified->getAttribute('a'))->toBe(1);
    });

    it('withAttributes merges into a new instance', function () {
        $original = new Context(['a' => 1]);

        $modified = $original->withAttributes(['a' => 10, 'b' => 2]);

        expect($original->getAttribute('a'))->toBe(1)
            ->and($modified->getAttribute('a'))->toBe(10)
            ->and($modified->getAttribute('b'))->toBe(2);
    });

    it('withoutAttribute returns a new instance without the key', function () {
        $original = new Context(['a' => 1, 'b' => 2]);

        $modified = $original->withoutAttribute('a');

        expect($original->hasAttribute('a'))->toBeTrue()
            ->and($modified->hasAttribute('a'))->toBeFalse()
            ->and($modified->getAttribute('b'))->toBe(2);
    });
});
