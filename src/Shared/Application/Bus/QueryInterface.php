<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

/**
 * Marker interface for queries (read operations).
 * Queries fetch data without causing side effects.
 */
interface QueryInterface
{
}
