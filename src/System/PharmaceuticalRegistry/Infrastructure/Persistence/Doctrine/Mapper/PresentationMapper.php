<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Mapper;

use App\System\PharmaceuticalRegistry\Domain\Entity\Presentation;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\EuPackIdentifier;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\Gtin;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\JurisdictionalPrescriptionCode;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\JurisdictionCode;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\Packaging;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\PrescriptionClass;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\PrescriptionRequirement;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\PresentationDescription;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\PresentationId;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\UnitCount;
use App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity\AuthorizationEntity;
use App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity\PresentationEntity;
use Symfony\Component\Uid\Uuid;

final readonly class PresentationMapper
{
    public function toDomain(PresentationEntity $entity): Presentation
    {
        $prescriptionRequirement = $this->mapPrescriptionRequirement($entity);

        return Presentation::create(
            id: PresentationId::fromString($entity->getId()->toString()),
            description: PresentationDescription::fromString($entity->getDescription()),
            unitCount: null !== $entity->getUnitCount() ? UnitCount::fromString($entity->getUnitCount()) : null,
            packaging: null !== $entity->getPackaging() ? Packaging::fromString($entity->getPackaging()) : null,
            gtin: null !== $entity->getGtin() ? Gtin::fromString($entity->getGtin()) : null,
            euPackIdentifier: null !== $entity->getEuPackIdentifier() ? EuPackIdentifier::fromString($entity->getEuPackIdentifier()) : null,
            prescriptionRequirement: $prescriptionRequirement,
        );
    }

    public function toEntity(Presentation $presentation, AuthorizationEntity $authorization): PresentationEntity
    {
        $entity = new PresentationEntity();
        $entity->setId(Uuid::fromString($presentation->id()->toString()));
        $entity->setAuthorization($authorization);
        $entity->setDescription($presentation->description()->toString());
        $entity->setUnitCount($presentation->unitCount()?->toString());
        $entity->setPackaging($presentation->packaging()?->toString());
        $entity->setGtin($presentation->gtin()?->toString());
        $entity->setEuPackIdentifier($presentation->euPackIdentifier()?->toString());

        $req = $presentation->prescriptionRequirement();
        $entity->setPrescriptionRequired($req->isRequired());
        $entity->setPrescriptionClass($req->prescriptionClass()->value);
        $entity->setPrescriptionRetentionYears($req->retention()?->durationYears());

        if (null !== $req->jurisdictionalCode()) {
            $entity->setPrescriptionJurisJurisdiction($req->jurisdictionalCode()->jurisdictionCode()->toString());
            $entity->setPrescriptionJurisCode($req->jurisdictionalCode()->code());
        } else {
            $entity->setPrescriptionJurisJurisdiction(null);
            $entity->setPrescriptionJurisCode(null);
        }

        return $entity;
    }

    private function mapPrescriptionRequirement(PresentationEntity $entity): PrescriptionRequirement
    {
        $class = PrescriptionClass::from($entity->getPrescriptionClass());

        if (PrescriptionClass::NONE === $class) {
            return PrescriptionRequirement::none();
        }

        $jurisdictionalCode = null;

        if (null !== $entity->getPrescriptionJurisJurisdiction() && null !== $entity->getPrescriptionJurisCode()) {
            $jurisdictionalCode = JurisdictionalPrescriptionCode::of(
                JurisdictionCode::fromString($entity->getPrescriptionJurisJurisdiction()),
                $entity->getPrescriptionJurisCode(),
            );
        }

        if (null === $jurisdictionalCode) {
            return PrescriptionRequirement::none();
        }

        if (PrescriptionClass::RX_NARCOTIC === $class) {
            return PrescriptionRequirement::narcotic($jurisdictionalCode);
        }

        if (null !== $entity->getPrescriptionRetentionYears()) {
            return PrescriptionRequirement::rxWithRetention($class, $jurisdictionalCode, $entity->getPrescriptionRetentionYears());
        }

        return PrescriptionRequirement::rx($class, $jurisdictionalCode);
    }
}
