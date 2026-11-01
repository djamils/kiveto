<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Support;

use App\Context\ClientPortal\Application\Port\ClinicLabel;
use App\Context\ClientPortal\Application\Port\ListedDocument;
use App\Context\ClientPortal\Application\Port\PortalPet;
use App\Context\ClientPortal\Application\Port\PreventiveAct;
use App\Context\ClientPortal\Application\Service\CareItems;
use App\Context\ClientPortal\Application\Service\CareItemStatusPolicy;
use App\Context\ClientPortal\Application\Service\OwnerPets;
use App\Context\ClientPortal\Domain\Link\ClientLinkStatus;

/**
 * The pet reads of an owner account on in-memory doubles: the account is
 * linked (confirmed) to CLIENT, the clinic is in Europe/Paris, the clock at
 * 2026-10-29 09:00 UTC.
 */
final class PetWorld
{
    public const string CLINIC  = DocumentWorld::CLINIC;
    public const string CLIENT  = DocumentWorld::CLIENT;
    public const string CLIENT2 = DocumentWorld::CLIENT2;
    public const string USER    = DocumentWorld::USER;
    public const string PET     = '01960000-0000-7000-8000-0000000a0001';
    public const string PET2    = '01960000-0000-7000-8000-0000000a0002';
    public const string PET3    = '01960000-0000-7000-8000-0000000a0003';

    public DocumentWorld $documents;
    public PetFakes $fakes;
    public FakePublishedDocumentReadModel $model;

    public function __construct(string $timeZone = 'Europe/Paris')
    {
        $this->documents = new DocumentWorld();
        $this->fakes     = new PetFakes();
        $this->model     = new FakePublishedDocumentReadModel();
        $this->documents->link();
        $this->documents->fakes->clinics[self::CLINIC] = new ClinicLabel('Clinique des Lilas', 'Lyon', $timeZone);
    }

    public function now(string $utc): void
    {
        $this->documents->clock->now = new \DateTimeImmutable($utc, new \DateTimeZone('UTC'));
    }

    public function link(string $clientId, ClientLinkStatus $status = ClientLinkStatus::CONFIRMED, string $clinicId = self::CLINIC): void
    {
        $this->documents->link($clientId, $status, $clinicId);
    }

    public function pet(
        string $id = self::PET,
        string $name = 'Moka',
        string $lifeStatus = 'alive',
        string $clientId = self::CLIENT,
        ?string $chip = null,
    ): PortalPet {
        return $this->fakes->pets[] = new PortalPet(
            $id,
            $clientId,
            $name,
            'cat',
            'Chartreux',
            false,
            'female',
            'neutered',
            new \DateTimeImmutable('2020-05-01'),
            $lifeStatus,
            $chip,
        );
    }

    public function act(string $petId, string $label, string $administeredOn, ?string $dueOn, string $kind = 'vaccination'): void
    {
        $this->fakes->acts[] = new PreventiveAct(
            $petId,
            $kind,
            $label,
            new \DateTimeImmutable($administeredOn),
            null === $dueOn ? null : new \DateTimeImmutable($dueOn),
        );
    }

    /**
     * A listable report row (its source is the consultation).
     */
    public static function report(string $id, string $consultationId, string $issuedAt, ?string $practitioner = 'Dr Claire Lambert', string $type = 'report'): ListedDocument
    {
        return new ListedDocument(
            $id,
            $type,
            'report' === $type ? $consultationId : $id,
            'Compte rendu ' . $id,
            [self::PET],
            new \DateTimeImmutable($issuedAt, new \DateTimeZone('UTC')),
            false,
            1,
            'client_portal/documents/' . $id . '-v1.pdf',
            $practitioner,
            $consultationId,
        );
    }

    public function ownerPets(): OwnerPets
    {
        return new OwnerPets($this->documents->links, $this->fakes);
    }

    public function careItems(): CareItems
    {
        return new CareItems($this->fakes, new CareItemStatusPolicy(), $this->documents->fakes, $this->documents->clock, $this->fakes);
    }
}
