<?php

declare(strict_types=1);

namespace Componenta\Policy\Exception;

use RuntimeException;

abstract class PolicyException extends RuntimeException implements PolicyExceptionInterface
{
}