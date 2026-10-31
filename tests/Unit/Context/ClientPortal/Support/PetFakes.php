<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Support;

use App\Context\ClientPortal\Application\Port\AnimalWeightsInterface;
use App\Context\ClientPortal\Application\Port\BookletFileRepositoryInterface;
use App\Context\ClientPortal\Application\Port\BookletRendererInterface;
use App\Context\ClientPortal\Application\Port\ConsultationWeightsInterface;
use App\Context\ClientPortal\Application\Port\OwnerAppointmentsInterface;
use App\Context\ClientPortal\Application\Port\PetDirectoryInterface;
use App\Context\ClientPortal\Application\Port\PetIdentity;
use App\Context\ClientPortal\Application\Port\PortalPet;
use App\Context\ClientPortal\Application\Port\PractitionerNamesInterface;
use App\Context\ClientPortal\Application\Port\PreventiveAct;
use App\Context\ClientPortal\Application\Port\PreventiveCareInterface;
use App\Context\ClientPortal\Application\Port\RecordedWeight;
use App\Context\ClientPortal\Application\Port\UpcomingAppointment;
use App\Context\ClientPortal\Application\Port\VaccinationBooklet;
use App\Context\ClientPortal\Application\Port\VaccinationRecord;
use App\Context\ClientPortal\Application\Port\VaccinationRecordsInterface;
use App\Context\ClientPortal\Application\Port\VisitSummariesInterface;
use App\Context\ClientPortal\Application\Port\VisitSummary;
use App\Context\ClientPortal\Application\Port\VisitWeight;

/**
 * The other contexts behind the pet reads, on public maps; every call is
 * recorded as `<method>:<args>`.
 */
final class PetFakes implements
    PetDirectoryInterface,
    PreventiveCareInterface,
    AnimalWeightsInterface,
    ConsultationWeightsInterface,
    VisitSummariesInterface,
    OwnerAppointmentsInterface,
    PractitionerNamesInterface,
    VaccinationRecordsInterface,
    BookletRendererInterface,
    BookletFileRepositoryInterface
{
    /** @var list<PortalPet> every pet of the clinic, in the directory's order */
    public array $pets = [];

    /** @var array<string, PetIdentity> by animal id */
    public array $identities = [];

    /** @var list<PreventiveAct> */
    public array $acts = [];

    /** @var array<string, RecordedWeight> by animal id */
    public array $recordedWeights = [];

    /** @var array<string, VisitWeight> by animal id */
    public array $visitWeights = [];

    /** @var array<string, VisitSummary> by consultation id */
    public array $visits = [];

    public ?UpcomingAppointment $appointment = null;

    /** @var array<string, string> by user id */
    public array $practitioners = [];

    /** @var array<string, list<VaccinationRecord>> by animal id */
    public array $vaccinations = [];

    /** @var list<VaccinationBooklet> */
    public array $rendered = [];

    /** @var array<string, string> current booklet key by animal id */
    public array $booklets = [];

    /** @var list<string> */
    public array $calls = [];

    public function visiblePets(string $clinicId, array $clientIds): array
    {
        $this->calls[] = 'visiblePets:' . $clinicId . ':' . implode(',', $clientIds);

        return array_values(array_filter($this->pets, static fn (PortalPet $pet): bool => \in_array($pet->primaryOwnerClientId, $clientIds, true)));
    }

    public function identityOf(string $clinicId, string $animalId): ?PetIdentity
    {
        return $this->identities[$animalId] ?? null;
    }

    public function latestActs(string $clinicId, array $animalIds): array
    {
        $this->calls[] = 'latestActs:' . $clinicId . ':' . implode(',', $animalIds);

        return array_values(array_filter($this->acts, static fn (PreventiveAct $act): bool => \in_array($act->animalId, $animalIds, true)));
    }

    public function latestOf(string $clinicId, string $animalId): ?RecordedWeight
    {
        return $this->recordedWeights[$animalId] ?? null;
    }

    public function latestClosedOf(string $clinicId, string $animalId): ?VisitWeight
    {
        return $this->visitWeights[$animalId] ?? null;
    }

    public function summariesOf(string $clinicId, array $consultationIds): array
    {
        $this->calls[] = 'summariesOf:' . $clinicId . ':' . implode(',', $consultationIds);

        return array_intersect_key($this->visits, array_flip($consultationIds));
    }

    public function nextFor(string $clinicId, array $animalIds, \DateTimeImmutable $nowUtc): ?UpcomingAppointment
    {
        $this->calls[] = 'nextFor:' . $clinicId . ':' . implode(',', $animalIds) . ':' . $nowUtc->format('c');

        return $this->appointment;
    }

    public function namesOf(string $clinicId, array $userIds): array
    {
        $this->calls[] = 'namesOf:' . $clinicId . ':' . implode(',', $userIds);

        return array_intersect_key($this->practitioners, array_flip($userIds));
    }

    public function vaccinationsOf(string $clinicId, string $animalId): array
    {
        return $this->vaccinations[$animalId] ?? [];
    }

    public function render(VaccinationBooklet $booklet): string
    {
        $this->rendered[] = $booklet;

        return '%PDF booklet ' . \count($this->rendered);
    }

    public function currentKey(string $clinicId, string $animalId): ?string
    {
        return $this->booklets[$animalId] ?? null;
    }

    public function saveCurrent(string $clinicId, string $animalId, string $storageKey, \DateTimeImmutable $at): void
    {
        $this->calls[]             = 'saveCurrent:' . $clinicId . ':' . $animalId . ':' . $storageKey . ':' . $at->format('c');
        $this->booklets[$animalId] = $storageKey;
    }
}
