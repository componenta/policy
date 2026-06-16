<?php

declare(strict_types=1);

namespace Componenta\Policy\Context;

enum ContextValueType: string
{
    case String = 'string';
    case Int = 'int';
    case Float = 'float';
    case Bool = 'bool';
    case Array = 'array';
    case Object = 'object';
    case Callable = 'callable';
    case Iterable = 'iterable';
    case Null = 'null';
}