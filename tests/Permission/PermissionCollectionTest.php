<?php

declare(strict_types=1);

use Componenta\Policy\ContainsMode;
use Componenta\Policy\Permission\PermissionCollection;
use Componenta\Policy\Tests\Fixture\FakePermission;

it('contains a permission after it is added', function () {
    $collection = new PermissionCollection();
    $collection->add(new FakePermission('posts.create'));

    expect($collection->contains('posts.create'))->toBeTrue();
});

it('accepts either a PermissionInterface instance or a name string in contains()', function () {
    $permission = new FakePermission('posts.create');
    $collection = new PermissionCollection([$permission]);

    expect($collection->contains($permission))->toBeTrue()
        ->and($collection->contains('posts.create'))->toBeTrue()
        ->and($collection->contains('other'))->toBeFalse();
});

it('treats permissions as a set keyed by name, replacing duplicate additions', function () {
    $first = new FakePermission('a');
    $replacement = new FakePermission('a');
    $b = new FakePermission('b');

    $collection = new PermissionCollection([$first, $replacement, $b]);

    expect($collection->count())->toBe(2)
        ->and($collection->toArray())->toBe(['a' => $replacement, 'b' => $b]);
});

it('checks another collection using any and all modes', function () {
    $collection = new PermissionCollection([new FakePermission('a'), new FakePermission('b')]);
    $overlap = new PermissionCollection([new FakePermission('b'), new FakePermission('c')]);
    $subset = new PermissionCollection([new FakePermission('a'), new FakePermission('b')]);
    $empty = new PermissionCollection();

    expect($collection->contains($overlap))->toBeTrue()
        ->and($collection->contains($overlap, ContainsMode::ALL))->toBeFalse()
        ->and($collection->contains($subset, ContainsMode::ALL))->toBeTrue()
        ->and($collection->contains($empty, ContainsMode::ANY))->toBeFalse()
        ->and($collection->contains($empty, ContainsMode::ALL))->toBeTrue();
});

it('removes a permission by name, regardless of the passed instance identity', function () {
    $collection = new PermissionCollection([new FakePermission('a'), new FakePermission('b')]);

    // A different instance with the same name must still remove the entry - remove() is name-keyed.
    $collection->remove(new FakePermission('a'));

    expect($collection->contains('a'))->toBeFalse()
        ->and($collection->contains('b'))->toBeTrue();
});

it('accepts a name string in remove()', function () {
    $collection = new PermissionCollection([new FakePermission('a'), new FakePermission('b')]);

    $collection->remove('a');

    expect($collection->contains('a'))->toBeFalse()
        ->and($collection->contains('b'))->toBeTrue();
});

it('iterates yielding permission name as key and the instance as value', function () {
    $a = new FakePermission('a');
    $b = new FakePermission('b');
    $collection = new PermissionCollection([$a, $b]);

    expect(iterator_to_array($collection))->toBe(['a' => $a, 'b' => $b]);
});