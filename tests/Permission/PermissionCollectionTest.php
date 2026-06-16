<?php

declare(strict_types=1);

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

it('treats permissions as a set keyed by name, ignoring duplicate additions', function () {
    $collection = new PermissionCollection([
        new FakePermission('a'),
        new FakePermission('a'),
        new FakePermission('b'),
    ]);

    expect($collection->count())->toBe(2)
        ->and($collection->toArray())->toEqualCanonicalizing(['a', 'b']);
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
