<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\GetReminderDelay;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The clinic's reminder delay: `H24`, `H48` or `SAME_DAY_8` (string).
 */
final readonly class GetReminderDelay implements QueryInterface
{
    public function __construct(public string $clinicId)
    {
    }
}
