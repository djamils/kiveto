<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\GetOnlineAvailability;

final readonly class OnlineAvailability
{
    /**
     * @param list<OnlineAvailabilityDay> $days one per requested date, in order
     */
    public function __construct(
        public array $days,
    ) {
    }
}
