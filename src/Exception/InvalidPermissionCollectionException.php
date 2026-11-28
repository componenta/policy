<?php

declare(strict_types=1);

namespace Componenta\Policy\Exception;

/** A permission collection yielded an element that does not implement the permission contract. */
final class InvalidPermissionCollectionException extends PolicyException
{
}
