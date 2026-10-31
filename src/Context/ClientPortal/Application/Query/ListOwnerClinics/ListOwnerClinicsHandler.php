<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListOwnerClinics;

use App\Context\ClientPortal\Application\Port\ClinicProfileInterface;
use App\Context\ClientPortal\Application\Port\PetNamesInterface;
use App\Context\ClientPortal\Domain\Link\ClientLinkRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Several client files of one clinic (family members sharing a phone) make
 * one clinic entry, their animals merged.
 */
#[AsMessageHandler]
final readonly class ListOwnerClinicsHandler
{
    public function __construct(
        private ClientLinkRepositoryInterface $links,
        private ClinicProfileInterface $clinics,
        private PetNamesInterface $pets,
    ) {
    }

    /**
     * @return list<OwnerClinic>
     */
    public function __invoke(ListOwnerClinics $query): array
    {
        /** @var array<string, array{linkedAt: \DateTimeImmutable, petNames: list<string>}> $byClinic */
        $byClinic = [];

        foreach ($this->links->findConfirmedByUser($query->userId) as $link) {
            $linkedAt = $link->linkedAt() ?? throw new \LogicException('A confirmed link has a link date.');
            $entry    = $byClinic[$link->clinicId()] ?? ['linkedAt' => $linkedAt, 'petNames' => []];

            $byClinic[$link->clinicId()] = [
                'linkedAt' => min($entry['linkedAt'], $linkedAt),
                'petNames' => [...$entry['petNames'], ...$this->pets->petNamesOf($link->clinicId(), $link->clientId())],
            ];
        }

        $clinics = [];

        foreach ($byClinic as $clinicId => $entry) {
            $clinic    = $this->clinics->clinicOf($clinicId);
            $clinics[] = new OwnerClinic($clinicId, $clinic->name, $clinic->city, $entry['linkedAt'], $entry['petNames'], $clinic->timeZone);
        }

        return $clinics;
    }
}
