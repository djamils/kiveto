<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\ListAnimalDirectoryFacts;

final readonly class AnimalDirectoryFacts
{
    /**
     * @param list<DirectoryAlertView>    $alerts       allergies first, then chronic conditions
     * @param list<DirectoryReminderView> $dueReminders soonest first
     */
    public function __construct(
        public array $alerts,
        public array $dueReminders,
    ) {
    }
}
