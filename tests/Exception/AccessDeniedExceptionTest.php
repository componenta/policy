<?php

declare(strict_types=1);

use Componenta\Policy\Context\Context;
use Componenta\Policy\Exception\AccessDenied;
use Componenta\Policy\Exception\AccessDeniedException;
use Componenta\Policy\Exception\DenyReason;
use Componenta\Policy\Tests\Fixture\RecordingPolicy;

it('exposes the underlying denial and defaults to HTTP 403', function () {
    $denied = new AccessDenied(
        actionId: 'posts.delete',
        reason: new DenyReason('not permitted', RecordingPolicy::class),
        actor: new stdClass(),
        context: new Context(),
    );

    $exception = AccessDeniedException::fromDenied($denied);

    expect($exception->denied)->toBe($denied)
        ->and($exception->getCode())->toBe(403)
        ->and($exception->getMessage())
            ->toContain('posts.delete')
            ->toContain('not permitted');
});

it('preserves every non-empty denial reason including a zero string', function (string $reason, string $expected): void {
    $denied = new AccessDenied('posts.delete', new DenyReason($reason), new stdClass(), new Context());
    $previous = new RuntimeException('Previous failure');
    $exception = new AccessDeniedException($denied, 403, $previous);

    expect($exception->getMessage())->toBe('Access denied for action "posts.delete": ' . $expected)
        ->and($exception->denied)->toBe($denied)
        ->and($exception->getCode())->toBe(403)
        ->and($exception->getPrevious())->toBe($previous);
})->with([
    ['0', '0'],
    ['00', '00'],
    ['not permitted', 'not permitted'],
    ['', 'No reason provided'],
]);
