<?php

declare(strict_types=1);

use Componenta\Arrayable\Arrayable;
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

it('returns all attributes as a plain associative array via toArray()', function () {
    $context = new Context(['a' => 1, 'b' => 'two']);

    expect($context->toArray())->toBe(['a' => 1, 'b' => 'two']);
});

it('implements the shared Arrayable contract', function () {
    expect(new Context())->toBeInstanceOf(Arrayable::class);
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

it('does not retain top-level references to attributes from constructor input or exported arrays', function (): void {
    $value = 'original';
    $attributes = ['a' => &$value];
    $context = new Context($attributes);
    $value = 'changed';
    $exported = $context->toArray();
    $exported['a'] = 'overwritten';

    expect($context->getAttribute('a'))->toBe('original');
});

it('detaches attribute references added through withAttributes', function (): void {
    $value = 'original';
    $base = new Context(['a' => 'base']);
    $derived = $base->withAttributes(['a' => &$value]);
    $value = 'changed';
    $exported = $derived->toArray();
    $exported['a'] = 'overwritten';

    expect($base->getAttribute('a'))->toBe('base')
        ->and($derived->getAttribute('a'))->toBe('original');
});

it('preserves numeric string attribute names across immutable updates', function (): void {
    $base = (new Context())->withAttribute('42', true);
    $derived = $base->withAttribute('other', false)->withAttributes(['42' => 'updated', '99' => 'new']);

    expect($base->getAttribute('42'))->toBeTrue()
        ->and($derived->getAttribute('42'))->toBe('updated')
        ->and($derived->getAttribute('99'))->toBe('new')
        ->and($derived->getAttribute('other'))->toBeFalse()
        ->and($derived->withoutAttribute('42')->hasAttribute('42'))->toBeFalse()
        ->and($derived->hasAttribute('42'))->toBeTrue()
        ->and($derived->hasAttribute('0'))->toBeFalse();
});

it('preserves resource identity when creating or deriving a context', function (): void {
    $resource = new stdClass();
    $context = new Context(['resource' => $resource]);

    expect($context->getAttribute('resource'))->toBe($resource)
        ->and($context->withAttribute('x', 1)->getAttribute('resource'))->toBe($resource);
});
