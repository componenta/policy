<?php

declare(strict_types=1);

use Componenta\Arrayable\Arrayable;
use Componenta\Policy\Context\Context;
use Componenta\Policy\Exception\ContextCapacityException;

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

it('isolates nested references on construction and on every read', function (): void {
    $allowed = false;
    $resource = new stdClass();
    $context = new Context(['metadata' => ['allowed' => &$allowed, 'resource' => $resource]]);
    $allowed = true;

    expect($context->getAttribute('metadata')['allowed'])->toBeFalse();

    $read = $context->getAttribute('metadata');
    $read['allowed'] = true;
    $export = $context->toArray();
    $export['metadata']['allowed'] = true;

    expect($context->getAttribute('metadata')['allowed'])->toBeFalse()
        ->and($context->getAttribute('metadata')['resource'])->toBe($resource);
});

it('isolates nested references when deriving contexts', function (): void {
    $allowed = false;
    $base = new Context();
    $first = $base->withAttribute('metadata', ['allowed' => &$allowed]);
    $second = $base->withAttributes(['metadata' => ['allowed' => &$allowed]]);
    $third = $first->withAttribute('extra', true);
    $allowed = true;

    foreach ([$first, $second, $third, $third->withoutAttribute('extra')] as $context) {
        $read = $context->getAttribute('metadata');
        $read['allowed'] = true;

        expect($context->getAttribute('metadata')['allowed'])->toBeFalse();
    }

    expect($base->hasAttribute('metadata'))->toBeFalse();
});

it('preserves internal array aliases without exposing the stored reference cells', function (): void {
    $value = 1;
    $context = new Context(['metadata' => ['first' => &$value, 'second' => &$value]]);
    $read = $context->getAttribute('metadata');
    $read['first'] = 2;

    expect($read['second'])->toBe(2)
        ->and($context->getAttribute('metadata')['first'])->toBe(1)
        ->and($context->getAttribute('metadata')['second'])->toBe(1)
        ->and($value)->toBe(1);
});

it('preserves recursive arrays without retaining or exposing their reference cells', function (): void {
    $recursive = ['value' => 1];
    $recursive['self'] = &$recursive;
    $context = new Context(['metadata' => $recursive]);
    $recursive['value'] = 2;

    $read = $context->getAttribute('metadata');
    $read['self']['value'] = 3;

    expect($read['self']['self']['value'])->toBe(3)
        ->and($context->getAttribute('metadata')['self']['value'])->toBe(1);

    $export = $context->toArray();
    $export['metadata']['self']['value'] = 4;

    expect($context->getAttribute('metadata')['self']['value'])->toBe(1);
});

it('handles mutual recursion whose original reference variables no longer exist', function (): void {
    $mutual = (static function (): array {
        $first = ['value' => 1];
        $second = ['value' => 2];
        $first['second'] = &$second;
        $second['first'] = &$first;

        return $first;
    })();
    $context = new Context(['metadata' => $mutual]);
    $read = $context->getAttribute('metadata');
    $read['second']['first']['value'] = 3;

    expect($read['second']['first']['second']['first']['value'])->toBe(3)
        ->and($context->getAttribute('metadata')['second']['first']['value'])->toBe(1);
});

it('handles deeply nested reference-containing arrays without recursive function calls', function (): void {
    $value = 1;
    $nested = ['value' => &$value];
    for ($i = 0; $i < 300; $i++) {
        $nested = ['nested' => $nested];
    }
    $context = new Context(['metadata' => $nested]);
    $value = 2;
    $read = $context->getAttribute('metadata');
    for ($i = 0; $i < 300; $i++) {
        $read = $read['nested'];
    }

    expect($read['value'])->toBe(1);
});

it('keeps ordinary array keys and resource identity', function (): void {
    $stream = fopen('php://memory', 'w+');
    try {
        $flag = false;
        $context = new Context(['metadata' => [42 => 'answer', 'stream' => $stream, 'flag' => &$flag]]);

        expect($context->getAttribute('metadata')[42])->toBe('answer')
            ->and($context->getAttribute('metadata')['stream'])->toBe($stream);
    } finally {
        fclose($stream);
    }
});

it('isolates hidden single-owner reference cells even when array padding makes them visible', function (): void {
    $metadata = (static function (): array {
        $value = 1;

        return ['value' => &$value];
    })();
    $context = new Context(['metadata' => $metadata]);

    $paddedInput = array_pad($metadata, 2, null);
    $paddedInput['value'] = 2;
    expect($context->getAttribute('metadata')['value'])->toBe(1);

    $paddedRead = array_pad($context->getAttribute('metadata'), 2, null);
    $paddedRead['value'] = 3;
    expect($context->getAttribute('metadata')['value'])->toBe(1);

    $export = $context->toArray();
    $paddedExport = array_pad($export['metadata'], 2, null);
    $paddedExport['value'] = 4;
    expect($context->getAttribute('metadata')['value'])->toBe(1);
});

it('preserves reference aliases across nested sibling arrays while isolating successive exports', function (): void {
    $shared = ['value' => 1];
    $left = ['slot' => &$shared];
    $right = ['slot' => &$shared];
    $context = new Context(['metadata' => ['left' => $left, 'right' => $right]]);
    $first = $context->toArray();
    $second = $context->toArray();
    $first['metadata']['left']['slot']['value'] = 2;

    expect($first['metadata']['right']['slot']['value'])->toBe(2)
        ->and($second['metadata']['right']['slot']['value'])->toBe(1)
        ->and($context->getAttribute('metadata')['right']['slot']['value'])->toBe(1)
        ->and($shared['value'])->toBe(1);
});

it('bounds inspection of copy-on-write array trees without references', function (): void {
    $tree = [1];
    for ($i = 0; $i < 16; $i++) {
        $tree = [$tree, $tree];
    }

    expect(fn () => new Context(['tree' => $tree]))->toThrow(ContextCapacityException::class);
});

it('bounds duplication of copy-on-write array trees containing references', function (): void {
    $value = 1;
    $tree = [1];
    for ($i = 0; $i < 16; $i++) {
        $tree = [$tree, $tree];
    }

    expect(fn () => new Context(['tree' => ['reference' => &$value, 'payload' => $tree]]))
        ->toThrow(ContextCapacityException::class);
});

it('shares the inspection budget across all attributes and rejects oversized derived contexts', function (): void {
    $payload = array_fill(0, 60_000, 1);
    $context = new Context(['first' => $payload]);

    expect(fn () => new Context(['first' => $payload, 'second' => $payload]))->toThrow(ContextCapacityException::class)
        ->and(fn () => $context->withAttribute('second', $payload))->toThrow(ContextCapacityException::class)
        ->and(fn () => $context->withAttributes(['second' => $payload]))->toThrow(ContextCapacityException::class)
        ->and($context->hasAttribute('second'))->toBeFalse()
        ->and($context->getAttribute('first'))->toBe($payload);
});

it('shares the reference-copy budget across attributes even when inspection stops early', function (): void {
    $value = 1;
    $payload = ['reference' => &$value, 'payload' => array_fill(0, 60_000, 1)];

    expect(fn () => new Context(['first' => $payload, 'second' => $payload]))->toThrow(ContextCapacityException::class);
});

it('accepts the exact inspection limit including the top-level entries', function (): void {
    $payload = array_fill(0, 99_999, 1);
    $context = new Context(['payload' => $payload]);

    expect($context->getAttribute('payload'))->toBe($payload)
        ->and(fn () => new Context(['payload' => array_fill(0, 100_000, 1)]))->toThrow(ContextCapacityException::class);
});

it('can safely read and export referenced arrays at the copy budget boundary', function (): void {
    $value = 1;
    $context = new Context(['data' => ['reference' => &$value, 'payload' => array_fill(0, 99_998, 1)]]);
    $value = 2;

    $read = $context->getAttribute('data');
    $export = $context->toArray();
    expect($read['reference'])->toBe(1)
        ->and($export['data']['reference'])->toBe(1)
        ->and($read['payload'])->toHaveCount(99_998);
});
