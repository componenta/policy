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
