<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Support;

use App\Context\Scheduling\Application\Port\ClinicCard;
use App\Context\Scheduling\Application\Port\ClinicDirectoryInterface;

/**
 * Clinique Montmartre (Europe/Paris unless told otherwise); practitioners
 * printed by user id.
 */
final class FakeClinicDirectory implements ClinicDirectoryInterface
{
    /**
     * @param array<string, string> $printedNames
     */
    public function __construct(
        public string $timeZone = 'Europe/Paris',
        public array $printedNames = [],
    ) {
    }

    public function clinic(string $clinicId): ClinicCard
    {
        return new ClinicCard('Clinique Montmartre', new \DateTimeZone($this->timeZone), 'accueil@clinique-montmartre.fr', '18 rue Lepic, 75018 Paris');
    }

    public function printedName(string $clinicId, string $userId): ?string
    {
        return $this->printedNames[$userId] ?? null;
    }
}
