<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\ListAnimalDirectoryFacts;

final readonly class DirectoryReminderView
{
    public function __construct(
        public string $label,
        public \DateTimeImmutable $nextDueOn,
    ) {
    }
}
