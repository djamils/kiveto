<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Dashboard;

/**
 * The free end of the day, after the last appointment (« Le reste de la
 * journée est libre »).
 */
final readonly class TimelineRest
{
    public function __construct(
        public string $title,
        public string $text,
    ) {
    }
}
