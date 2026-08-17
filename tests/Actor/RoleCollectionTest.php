<?php

declare(strict_types=1);

use Componenta\Policy\Actor\RoleCollection;
use Componenta\Policy\ContainsMode;
use Componenta\Policy\Tests\Fixture\FakeRole;

it('uses the RoleInterface name property as the collection key', function (): void {
    $admin = new FakeRole('admin');
    $collection = new RoleCollection([$admin]);

    expect($collection->contains('admin'))->toBeTrue()
        ->and($collection->contains($admin))->toBeTrue()
        ->and($collection->toArray())->toBe(['admin' => $admin]);
});

it('replaces and removes roles by their name property', function (): void {
    $first = new FakeRole('admin', ['first']);
    $replacement = new FakeRole('admin', ['second']);
    $collection = new RoleCollection([$first]);

    $collection->add($replacement);

    expect($collection->count())->toBe(1)
        ->and($collection->toArray()['admin'])->toBe($replacement);

    $collection->remove($replacement);

    expect($collection->count())->toBe(0);
});

it('supports collection containment including empty collection semantics', function (): void {
    $collection = new RoleCollection([
        new FakeRole('admin'),
        new FakeRole('editor'),
    ]);
    $subset = new RoleCollection([new FakeRole('editor')]);
    $missing = new RoleCollection([new FakeRole('moderator')]);
    $empty = new RoleCollection();

    expect($collection->contains($subset, ContainsMode::ANY))->toBeTrue()
        ->and($collection->contains($subset, ContainsMode::ALL))->toBeTrue()
        ->and($collection->contains($missing, ContainsMode::ANY))->toBeFalse()
        ->and($collection->contains($empty, ContainsMode::ANY))->toBeFalse()
        ->and($collection->contains($empty, ContainsMode::ALL))->toBeTrue();
});
