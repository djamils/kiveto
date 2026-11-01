<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetOwnerHome;

use App\Context\ClientPortal\Application\Port\OwnerAppointmentsInterface;
use App\Context\ClientPortal\Application\Port\PortalPet;
use App\Context\ClientPortal\Application\Port\PublishedDocumentReadModelInterface;
use App\Context\ClientPortal\Application\Query\ListOwnerAppointments\PortalAppointment;
use App\Context\ClientPortal\Application\Query\ListOwnerPets\OwnerPet;
use App\Context\ClientPortal\Application\Query\ListPublishedDocuments\ListPublishedDocumentsHandler;
use App\Context\ClientPortal\Application\Service\CareItems;
use App\Context\ClientPortal\Application\Service\OwnerPets;
use App\Context\ClientPortal\Application\Service\PortalAppointments;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Deceased and missing pets are left out of every section. The latest
 * documents follow the document list's rules (scope, live states,
 * exclusions), so there can be fewer than two. The next appointment has
 * the full shape of the owner's appointments (slice 4).
 */
#[AsMessageHandler]
final readonly class GetOwnerHomeHandler
{
    public const int CARE_ITEMS = 3;

    public const int LATEST_DOCUMENTS = 2;

    public function __construct(
        private OwnerPets $pets,
        private CareItems $careItems,
        private OwnerAppointmentsInterface $appointments,
        private PortalAppointments $views,
        private PublishedDocumentReadModelInterface $documents,
        private ListPublishedDocumentsHandler $documentList,
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

    /**
     * @param list<PortalPet> $pets alive ones
     */
    private function nextAppointment(string $clinicId, array $pets): ?PortalAppointment
    {
        $petIds      = array_map(static fn (PortalPet $pet): string => $pet->id, $pets);
        $next        = $this->appointments->nextFor($clinicId, $petIds, $this->clock->now());
        $appointment = null === $next ? null : $this->appointments->find($clinicId, $next->id);

        if (null === $appointment || !\in_array($appointment->animalId, $petIds, true)) {
            return null;
        }

        return $this->views->views($clinicId, [$appointment], $pets)[0];
    }
}
