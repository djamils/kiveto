<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Admission;

use App\Context\Admission\Application\Port\AdmissionReadRepositoryInterface;
use App\Context\Admission\Application\Port\WaitingRoomItemDto;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ClinicVeterinarianItem;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ListClinicVeterinarians;
use App\Context\Scheduling\Application\Query\GetAgendaForClinicDateRange\AppointmentItem;
use App\Context\Scheduling\Application\Query\GetAgendaForClinicDateRange\GetAgendaForClinicDateRange;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/admission/queue', name: 'clinic_admission_queue', methods: ['GET'])]
final class AdmissionQueueController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly AdmissionReadRepositoryInterface $admissionReadRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $clinicId = $currentClinicId->toString();

        // ── Active admissions (all locations) ──────────────────────────────
        $entries = $this->admissionReadRepository->findAllActiveForClinic($clinicId);

        $countUnidentified = 0;
        foreach ($entries as $entry) {
            if (!$entry->isPatientIdentifiedAtOpening) {
                ++$countUnidentified;
            }
        }

        // ── Practitioner roster (real) ─────────────────────────────────────
        $veterinarians = $this->queryBus->ask(new ListClinicVeterinarians($clinicId));
        \assert(\is_array($veterinarians));

        /** @var list<ClinicVeterinarianItem> $veterinarians */
        $practitioners = $veterinarians;

        // ── Today's scheduled appointments (real) ─────────────────────────
        // Timezone: Europe/Paris as sensible default — per-clinic TZ wiring is a V2 TODO
        $clinicTz     = new \DateTimeZone('Europe/Paris');
        $today        = new \DateTimeImmutable('now', $clinicTz);
        $appointments = $this->queryBus->ask(
            GetAgendaForClinicDateRange::forDay($clinicId, $today, $clinicTz),
        );
        \assert(\is_array($appointments));

        $plannedAppointments = [];
        foreach ($appointments as $appt) {
            \assert($appt instanceof AppointmentItem);
            if ('PLANNED' === $appt->status && null === $appt->linkedAdmissionId) {
                $plannedAppointments[] = $appt;
            }
        }

        // ── Animal enrichment (species, breed, owner name/phone) ───────────
        $animalEnrichment = $this->enrichFromSearchEntries($entries, $clinicId);

        return $this->render('clinic/admission/queue.html.twig', [
            'entries'           => $entries,
            'countUnidentified' => $countUnidentified,
            'practitioners'     => $practitioners,
            'appointments'      => $plannedAppointments,
            'animalEnrichment'  => $animalEnrichment,
        ]);
    }

    /**
     * Batch-enriches admission entries with animal species/breed and owner
     * name/phone by querying the search read-model and client tables.
     * Runs two DBAL queries regardless of the number of entries.
     *
     * @param list<WaitingRoomItemDto> $entries
     *
     * @return array<string, array{species:string|null,breed:string|null,ownerName:string|null,ownerPhone:string|null}>
     */
    private function enrichFromSearchEntries(array $entries, string $clinicId): array
    {
        $animalIds = [];
        foreach ($entries as $entry) {
            if (null !== $entry->knownAnimalId) {
                $animalIds[$entry->admissionId] = $entry->knownAnimalId;
            }
        }

        if ([] === $animalIds) {
            return [];
        }

        $conn         = $this->entityManager->getConnection();
        $clinicBinary = Uuid::fromString($clinicId)->toBinary();

        $binAnimalIds = array_map(
            static fn (string $id): string => Uuid::fromString($id)->toBinary(),
            array_values($animalIds),
        );

        // Query animal__search_entries for species, breed, owner name + client id
        $searchRows = $conn->fetchAllAssociative(
            'SELECT BIN_TO_UUID(id) AS animal_id, species, breed_name,
                    search_owner_name, BIN_TO_UUID(primary_owner_client_id) AS owner_client_id
             FROM animal__search_entries
             WHERE clinic_id = :clinicId AND id IN (?)',
            [$clinicBinary, $binAnimalIds],
            [\Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ArrayParameterType::STRING],
        );

        /** @var array<string, array{species:string|null,breed:string|null,ownerName:string|null,ownerPhone:string|null}> $byAnimalId */
        $byAnimalId = [];
        $clientIds  = [];

        foreach ($searchRows as $row) {
            \assert(\is_string($row['animal_id']));
            $byAnimalId[$row['animal_id']] = [
                'species'       => \is_string($row['species']) ? $row['species'] : null,
                'breed'         => \is_string($row['breed_name']) ? $row['breed_name'] : null,
                'ownerName'     => \is_string($row['search_owner_name']) ? $row['search_owner_name'] : null,
                'ownerPhone'    => null,
                'ownerClientId' => \is_string($row['owner_client_id']) ? $row['owner_client_id'] : null,
            ];

            if (\is_string($row['owner_client_id'])) {
                $clientIds[] = Uuid::fromString($row['owner_client_id'])->toBinary();
            }
        }

        // Fetch primary phone for each owner client
        if ([] !== $clientIds) {
            $phoneRows = $conn->fetchAllAssociative(
                "SELECT BIN_TO_UUID(client_id) AS client_id, value AS phone
                 FROM client__contact_methods
                 WHERE client_id IN (?) AND type = 'phone' AND is_primary = 1",
                [$clientIds],
                [\Doctrine\DBAL\ArrayParameterType::STRING],
            );

            /** @var array<string, string> $phoneByClient */
            $phoneByClient = [];
            foreach ($phoneRows as $pr) {
                \assert(\is_string($pr['client_id']));
                \assert(\is_string($pr['phone']));
                $phoneByClient[$pr['client_id']] = $pr['phone'];
            }

            foreach ($byAnimalId as $aId => &$data) {
                $cid = $data['ownerClientId'] ?? null;
                if (\is_string($cid) && isset($phoneByClient[$cid])) {
                    $data['ownerPhone'] = $phoneByClient[$cid];
                }
            }
            unset($data);
        }

        // Map by admission ID
        $result = [];
        foreach ($animalIds as $admissionId => $animalId) {
            if (isset($byAnimalId[$animalId])) {
                $d                    = $byAnimalId[$animalId];
                $result[$admissionId] = [
                    'species'    => $d['species'],
                    'breed'      => $d['breed'],
                    'ownerName'  => $d['ownerName'],
                    'ownerPhone' => $d['ownerPhone'],
                ];
            }
        }

        return $result;
    }
}
