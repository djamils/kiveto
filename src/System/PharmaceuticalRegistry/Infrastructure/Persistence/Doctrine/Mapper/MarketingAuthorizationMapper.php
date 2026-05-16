<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Mapper;

use App\System\PharmaceuticalRegistry\Domain\MarketingAuthorization;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\AtcVetCode;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\CommercialName;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ControlledSubstance;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ControlledSubstanceClass;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\HolderLaboratory;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ImportSource;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\JurisdictionalControlCode;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\JurisdictionalIdentifier;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\JurisdictionCode;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\MarketingAuthorizationDate;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\MarketingAuthorizationId;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\MarketingAuthorizationStatus;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\PermanentIdentifier;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\PharmaceuticalForm;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ProductNature;
use App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity\ActiveSubstanceEntity;
use App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity\AuthorizationEntity;
use App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity\CompositionEntity;
use App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity\JurisdictionEntity;
use App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity\PresentationEntity;
use App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity\SummaryEntity;
use App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity\TargetUsageEntity;
use Symfony\Component\Uid\Uuid;

final readonly class MarketingAuthorizationMapper
{
    public function __construct(
        private PresentationMapper $presentationMapper,
        private CompositionMapper $compositionMapper,
        private TargetUsageMapper $targetUsageMapper,
        private SummaryMapper $summaryMapper,
    ) {
    }

    /**
     * @param JurisdictionEntity[] $jurisdictions
     * @param PresentationEntity[] $presentations
     * @param CompositionEntity[]  $compositions
     * @param TargetUsageEntity[]  $targetUsages
     */
    public function toDomain(
        AuthorizationEntity $entity,
        array $jurisdictions,
        array $presentations,
        array $compositions,
        array $targetUsages,
        ?SummaryEntity $summary,
    ): MarketingAuthorization {
        $domainIdentifiers = array_map(
            static fn (JurisdictionEntity $j) => JurisdictionalIdentifier::of(
                JurisdictionCode::fromString($j->getJurisdictionCode()),
                $j->getAuthorityCode(),
                $j->getAuthorityIdentifier(),
            ),
            $jurisdictions,
        );

        $domainPresentations = array_map(
            fn (PresentationEntity $p) => $this->presentationMapper->toDomain($p),
            $presentations,
        );

        $domainCompositions = array_map(
            fn (CompositionEntity $c) => $this->compositionMapper->toDomain($c),
            $compositions,
        );

        $domainTargetUsages = array_map(
            fn (TargetUsageEntity $t) => $this->targetUsageMapper->toDomain($t),
            $targetUsages,
        );

        $domainSummary = null !== $summary ? $this->summaryMapper->toDomain($summary) : null;

        $controlledSubstance = null;

        if (null !== $entity->getControlledSubstanceClass()) {
            $controlledSubstance = ControlledSubstance::of(
                class: ControlledSubstanceClass::from($entity->getControlledSubstanceClass()),
                jurisdictionalCode: JurisdictionalControlCode::of(
                    JurisdictionCode::fromString((string) $entity->getControlledSubstanceJurisdiction()),
                    (string) $entity->getControlledSubstanceCode(),
                ),
                restrictions: $entity->getControlledSubstanceRestrictions(),
            );
        }

        return MarketingAuthorization::reconstitute(
            id: MarketingAuthorizationId::fromString($entity->getId()->toString()),
            commercialName: CommercialName::fromString($entity->getCommercialName()),
            holderLaboratory: HolderLaboratory::fromString($entity->getHolderLaboratory()),
            status: MarketingAuthorizationStatus::from($entity->getStatus()),
            authorizationDate: MarketingAuthorizationDate::fromDateTime($entity->getAuthorizationDate()),
            nature: ProductNature::from($entity->getNature()),
            pharmaceuticalForm: PharmaceuticalForm::fromString($entity->getPharmaceuticalForm()),
            atcVetCode: null !== $entity->getAtcVetCode() ? AtcVetCode::fromString($entity->getAtcVetCode()) : null,
            permanentIdentifier: null !== $entity->getPermanentIdentifier() ? PermanentIdentifier::fromString($entity->getPermanentIdentifier()) : null,
            controlledSubstance: $controlledSubstance,
            jurisdictionalIdentifiers: $domainIdentifiers,
            presentations: $domainPresentations,
            compositions: $domainCompositions,
            targetUsages: $domainTargetUsages,
            summary: $domainSummary,
            lastImportSource: ImportSource::from($entity->getLastImportSource()),
            lastImportedAt: $entity->getLastImportedAt(),
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
            contentHash: $entity->getContentHash(),
        );
    }

    /**
     * @param array<string, ActiveSubstanceEntity> $activeSubstanceMap indexed by ID
     */
    public function toEntity(MarketingAuthorization $ma, array $activeSubstanceMap = []): AuthorizationEntity
    {
        $entity = new AuthorizationEntity();
        $entity->setId(Uuid::fromString($ma->id()->toString()));
        $entity->setCommercialName($ma->commercialName()->toString());
        $entity->setHolderLaboratory($ma->holderLaboratory()->toString());
        $entity->setStatus($ma->status()->value);
        $entity->setAuthorizationDate($ma->authorizationDate()->toDateTime());
        $entity->setNature($ma->nature()->value);
        $entity->setPharmaceuticalForm($ma->pharmaceuticalForm()->toString());
        $entity->setAtcVetCode($ma->atcVetCode()?->toString());
        $entity->setPermanentIdentifier($ma->permanentIdentifier()?->toString());
        $entity->setContentHash($ma->contentHash());
        $entity->setLastImportSource($ma->lastImportSource()->value);
        $entity->setLastImportedAt($ma->lastImportedAt());
        $entity->setCreatedAt($ma->createdAt());
        $entity->setUpdatedAt($ma->updatedAt());

        $cs = $ma->controlledSubstance();

        if (null !== $cs) {
            $entity->setControlledSubstanceClass($cs->class()->value);
            $entity->setControlledSubstanceJurisdiction($cs->jurisdictionalCode()->jurisdictionCode()->toString());
            $entity->setControlledSubstanceCode($cs->jurisdictionalCode()->code());
            $entity->setControlledSubstanceRestrictions($cs->restrictions());
        } else {
            $entity->setControlledSubstanceClass(null);
            $entity->setControlledSubstanceJurisdiction(null);
            $entity->setControlledSubstanceCode(null);
            $entity->setControlledSubstanceRestrictions(null);
        }

        foreach ($ma->jurisdictionalIdentifiers() as $identifier) {
            $jurisdictionEntity = new JurisdictionEntity();
            $jurisdictionEntity->setId(Uuid::v7());
            $jurisdictionEntity->setAuthorization($entity);
            $jurisdictionEntity->setJurisdictionCode($identifier->jurisdictionCode()->toString());
            $jurisdictionEntity->setAuthorityCode($identifier->authorityCode());
            $jurisdictionEntity->setAuthorityIdentifier($identifier->authorityIdentifier());
            $entity->addJurisdiction($jurisdictionEntity);
        }

        foreach ($ma->presentations() as $presentation) {
            $presentationEntity = $this->presentationMapper->toEntity($presentation, $entity);
            $entity->addPresentation($presentationEntity);
        }

        foreach ($ma->compositions() as $composition) {
            $substanceId     = $composition->activeSubstanceId()->toString();
            $substanceEntity = $activeSubstanceMap[$substanceId] ?? null;

            if (null !== $substanceEntity) {
                $compositionEntity = $this->compositionMapper->toEntity($composition, $entity, $substanceEntity);
                $entity->addComposition($compositionEntity);
            }
        }

        foreach ($ma->targetUsages() as $targetUsage) {
            $targetUsageEntity = $this->targetUsageMapper->toEntity($targetUsage, $entity);
            $entity->addTargetUsage($targetUsageEntity);
        }

        if (null !== $ma->summary()) {
            $summaryEntity = $this->summaryMapper->toEntity($ma->summary(), $entity);
            $entity->setSummary($summaryEntity);
        }

        return $entity;
    }
}
