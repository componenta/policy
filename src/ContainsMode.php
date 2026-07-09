<?php

declare(strict_types=1);

namespace Componenta\Policy;

/**
 * Collection containment mode shared by policy and collection contracts.
 */
enum ContainsMode
{
    /** At least one required item must be present. */
    case ANY;

    /** Every required item must be present. */
    case ALL;
}