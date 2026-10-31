<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetOwnerHome;

use App\Context\ClientPortal\Application\Port\ClinicProfileInterface;
use App\Context\ClientPortal\Application\Port\OwnerAppointmentsInterface;
use App\Context\ClientPortal\Application\Port\PortalPet;
use App\Context\ClientPortal\Application\Port\PractitionerNamesInterface;
use App\Context\ClientPortal\Application\Port\PublishedDocumentReadModelInterface;
use App\Context\ClientPortal\Application\Query\ListOwnerPets\OwnerPet;
use App\Context\ClientPortal\Application\Query\ListPublishedDocuments\ListPublishedDocumentsHandler;
use App\Context\ClientPortal\Application\Service\CareItems;
use App\Context\ClientPortal\Application\Service\OwnerPets;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Deceased and missing pets are left out of every section. The latest
 * documents follow the document list's rules (scope, live states,
 * exclusions), so there can be fewer than two.
 */
#[AsMessageHandler]
final readonly class GetOwnerHomeHandler
{
    public const int CARE_ITEMS = 3;

    public const int LATEST_DOCUMENTS = 2;

    /** By the category Scheduling reads from the appointment reason. */
    private const array MOTIF_LABELS = [
        'vaccin' => 'Vaccination',
        'suivi'  => 'Contrôle',
        'bilan'  => 'Bilan',
        'chir'   => 'Chirurgie',
        'urg'    => 'Urgence',
    ];

    private const string DEFAULT_MOTIF_LABEL = 'Consultation';

    public function __construct(
        private OwnerPets $pets,
        private CareItems $careItems,
        private OwnerAppointmentsInterface $appointments,
        private PractitionerNamesInterface $practitioners,
        private PublishedDocumentReadModelInterface $documents,
        private ListPublishedDocumentsHandler $documentList,
        private ClinicProfileInterface $clinics,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(GetOwnerHome $query): OwnerHome
    {
        $pets = $this->pets->alive($query->userId, $query->clinicId);
        $rows = $this->documents->listable($query->userId, $query->clinicId, null, null, null, self::LATEST_DOCUMENTS);

        return new OwnerHome(
            $this->nextAppointment($query->clinicId, $pets),
            array_map(OwnerPet::of(...), $pets),
            \array_slice($this->careItems->of($query->clinicId, $pets), 0, self::CARE_ITEMS),
            $this->documentList->documents($query->clinicId, $rows),
        );
    }

    public static function motifLabel(string $category): string
    {
        return self::MOTIF_LABELS[$category] ?? self::DEFAULT_MOTIF_LABEL;
    }

    /**
     * @param list<PortalPet> $pets alive ones
     */
    private function nextAppointment(string $clinicId, array $pets): ?NextAppointment
    {
        $names = [];

        foreach ($pets as $pet) {
            $names[$pet->id] = $pet->name;
        }

        $appointment = $this->appointments->nextFor($clinicId, array_keys($names), $this->clock->now());
        $petName     = null === $appointment ? null : ($names[$appointment->animalId] ?? null);

        if (null === $appointment || null === $petName) {
            return null;
        }

        $practitioners = $this->practitioners->namesOf($clinicId, [$appointment->practitionerUserId]);

        return new NextAppointment(
            $appointment->id,
            $appointment->startsAtUtc->setTimezone(new \DateTimeZone($this->clinics->clinicOf($clinicId)->timeZone)),
            $appointment->animalId,
            $petName,
            $practitioners[$appointment->practitionerUserId] ?? null,
            self::motifLabel($appointment->category),
        );
    }
}
