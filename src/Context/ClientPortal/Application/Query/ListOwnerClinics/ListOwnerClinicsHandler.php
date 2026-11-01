<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListOwnerClinics;

use App\Context\ClientPortal\Application\Port\ClinicProfileInterface;
use App\Context\ClientPortal\Application\Port\PetNamesInterface;
use App\Context\ClientPortal\Domain\Link\ClientLinkRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Several client files of one clinic (family members sharing a phone) make
 * one clinic entry, their animals merged. A clinic with a confirmed link is
 * `confirmed`, whatever else is pending there; a clinic with pending links
 * only is `pending`, without link date nor animals (nothing of the clinic's
 * data is shown before it confirms). The oldest confirmed clinic comes
 * first, pending ones last.
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
        /** @var array<string, array{linkedAt: ?\DateTimeImmutable, petNames: list<string>}> $byClinic */
        $byClinic = [];

        foreach ($this->links->findActiveByUser($query->userId) as $link) {
            $entry = $byClinic[$link->clinicId()] ?? ['linkedAt' => null, 'petNames' => []];

            if ($link->isConfirmed()) {
                $linkedAt = $link->linkedAt() ?? throw new \LogicException('A confirmed link has a link date.');
                $entry    = [
                    'linkedAt' => null === $entry['linkedAt'] ? $linkedAt : min($entry['linkedAt'], $linkedAt),
                    'petNames' => [...$entry['petNames'], ...$this->pets->petNamesOf($link->clinicId(), $link->clientId())],
                ];
            }

            $byClinic[$link->clinicId()] = $entry;
        }

        uksort($byClinic, static fn (string $a, string $b): int => [null === $byClinic[$a]['linkedAt'], $byClinic[$a]['linkedAt'], $a] <=> [null === $byClinic[$b]['linkedAt'], $byClinic[$b]['linkedAt'], $b]);

        $clinics = [];

        foreach ($byClinic as $clinicId => $entry) {
            $clinicId  = (string) $clinicId;
            $clinic    = $this->clinics->clinicOf($clinicId);
            $clinics[] = new OwnerClinic(
                $clinicId,
                $clinic->name,
                $clinic->city,
                $entry['linkedAt'],
                $entry['petNames'],
                $clinic->timeZone,
                null === $entry['linkedAt'] ? OwnerClinic::PENDING : OwnerClinic::CONFIRMED,
            );
        }

        return $clinics;
    }
}
