<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListPetTimeline;

final readonly class TimelineDocument
{
    public function __construct(
        public string $id,
        public string $type,
    ) {
    }
}
