<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Clinic\UpdateClinicOpeningHours;

/**
 * The week the clinic keeps, replaced whole.
 */
final readonly class UpdateClinicOpeningHours
{
    /**
     * @param array<int, list<array{opensAt: string, closesAt: string}>> $week     keyed by ISO day, 1 to 7
     * @param list<array{date: string, reason?: string|null}>            $closures
     */
    public function __construct(
        public string $clinicId,
        public array $week,
        public array $closures = [],
    ) {
    }
}
