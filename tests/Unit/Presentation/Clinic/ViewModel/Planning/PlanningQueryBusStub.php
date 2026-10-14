<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Clinic\ViewModel\Planning;

use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ClinicVeterinarianItem;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ListClinicVeterinarians;
use App\Context\Clinic\Domain\ValueObject\ClinicStatus;
use App\Context\Scheduling\Application\Query\ListPlanningBlocksForClinicDateRange\ListPlanningBlocksForClinicDateRange;
use App\Context\Scheduling\Application\Query\ListPlanningBlocksForClinicDateRange\PlanningBlockView;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * Answers the clinic, its practitioners and three block occurrences;
 * remembers the block query it was asked.
 */
final class PlanningQueryBusStub implements QueryBusInterface
{
    public ?ListPlanningBlocksForClinicDateRange $blocks = null;

    public function ask(object $query, object ...$stamps): mixed
    {
        if ($query instanceof GetClinic) {
            return new ClinicDto(
                id: 'c1',
                name: 'Clinique',
                slug: 'clinique',
                timeZone: 'Europe/Paris',
                locale: 'fr',
                countryCode: 'FR',
                jurisdictionCode: null,
                currencyCode: 'EUR',
                status: ClinicStatus::ACTIVE,
                clinicGroupId: null,
                createdAt: '2026-01-01',
                updatedAt: '2026-01-01',
            );
        }

        if ($query instanceof ListClinicVeterinarians) {
            return [new ClinicVeterinarianItem(
                userId: 'u1',
                role: 'VETERINARIAN',
                engagement: 'EMPLOYEE',
                membershipId: 'm1',
                displayName: 'Claire Lambert',
                professionalTitle: 'DR',
                agendaColor: '#4f86c6',
            )];
        }

        \assert($query instanceof ListPlanningBlocksForClinicDateRange);
        $this->blocks = $query;

        return [
            $this->view('b1', 'break', '12:30', '14:00', 0, 'WEEKLY', '2026-04-22'),
            $this->view('b2', 'emergency', '18:30', '20:00', 2, 'NONE', '2026-10-14'),
            $this->view('b3', 'unknown', '08:00', '12:00', 4, 'NONE', '2026-10-14'),
        ];
    }

    private function view(string $id, string $type, string $start, string $end, int $cap, string $freq, string $base): PlanningBlockView
    {
        return new PlanningBlockView(
            id: $id,
            clinicId: 'c1',
            staffUserId: 'u1',
            type: $type,
            date: '2026-10-14',
            startTime: $start,
            endTime: $end,
            capacityPerHour: $cap,
            recurrenceFreq: $freq,
            recurrenceUntil: null,
            note: null,
            acceptsAppointments: false,
            hasCapacityLimit: false,
            baseDate: $base,
        );
    }
}
