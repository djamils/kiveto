<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Adapter\Clinic;

use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ClinicVeterinarianItem;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ListClinicVeterinarians;
use App\Context\Consultation\Application\Port\PractitionerDirectoryInterface;
use App\Context\Consultation\Application\Port\ReportPractitioner;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * The practitioner among the clinic's veterinarians (ListClinicVeterinarians).
 */
final readonly class QueryBusPractitionerDirectory implements PractitionerDirectoryInterface
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function find(string $clinicId, string $userId): ?ReportPractitioner
    {
        $veterinarians = $this->queryBus->ask(new ListClinicVeterinarians($clinicId));

        if (!\is_array($veterinarians)) {
            return null;
        }

        foreach ($veterinarians as $veterinarian) {
            if ($veterinarian instanceof ClinicVeterinarianItem && $veterinarian->userId === $userId) {
                return new ReportPractitioner($veterinarian->displayName, $veterinarian->professionalTitle);
            }
        }

        return null;
    }
}
