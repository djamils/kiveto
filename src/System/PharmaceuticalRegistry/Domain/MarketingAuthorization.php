<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain;

use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Event\IntegrationEventInterface;
use App\System\PharmaceuticalRegistry\Domain\Entity\Composition;
use App\System\PharmaceuticalRegistry\Domain\Entity\Presentation;
use App\System\PharmaceuticalRegistry\Domain\Entity\Summary;
use App\System\PharmaceuticalRegistry\Domain\Entity\TargetUsage;
use App\System\PharmaceuticalRegistry\Domain\Event\ControlledSubstanceClassificationChanged;
use App\System\PharmaceuticalRegistry\Domain\Event\MarketingAuthorizationCreated;
use App\System\PharmaceuticalRegistry\Domain\Event\MarketingAuthorizationStatusChanged;
use App\System\PharmaceuticalRegistry\Domain\Event\MarketingAuthorizationSuspended;
use App\System\PharmaceuticalRegistry\Domain\Event\MarketingAuthorizationUpdated;
use App\System\PharmaceuticalRegistry\Domain\Event\MarketingAuthorizationWithdrawn;
use App\System\PharmaceuticalRegistry\Domain\Event\PrescriptionRequirementChanged;
use App\System\PharmaceuticalRegistry\Domain\Event\PresentationAdded;
use App\System\PharmaceuticalRegistry\Domain\Event\PresentationGtinChanged;
use App\System\PharmaceuticalRegistry\Domain\Event\PresentationRemoved;
use App\System\PharmaceuticalRegistry\Domain\Event\TargetSpeciesAdded;
use App\System\PharmaceuticalRegistry\Domain\Event\WithdrawalPeriodChanged;
use App\System\PharmaceuticalRegistry\Domain\Exception\DuplicateJurisdictionalIdException;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\AtcVetCode;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\CommercialName;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ControlledSubstance;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\Gtin;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\HolderLaboratory;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ImportSource;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\JurisdictionalIdentifier;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\MarketingAuthorizationDate;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\MarketingAuthorizationId;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\MarketingAuthorizationStatus;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\PermanentIdentifier;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\PharmaceuticalForm;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\PrescriptionRequirement;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\PresentationDescription;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\PresentationId;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ProductNature;

class MarketingAuthorization extends AggregateRoot
{
    private MarketingAuthorizationId $id;
    private CommercialName $commercialName;
    private HolderLaboratory $holderLaboratory;
    private MarketingAuthorizationStatus $status;
    private MarketingAuthorizationDate $authorizationDate;
    private ProductNature $nature;
    private PharmaceuticalForm $pharmaceuticalForm;
    private ?AtcVetCode $atcVetCode;
    private ?PermanentIdentifier $permanentIdentifier;
    private ?ControlledSubstance $controlledSubstance;
    /** @var JurisdictionalIdentifier[] */
    private array $jurisdictionalIdentifiers;
    /** @var Presentation[] */
    private array $presentations;
    /** @var Composition[] */
    private array $compositions;
    /** @var TargetUsage[] */
    private array $targetUsages;
    private ?Summary $summary;
    private ImportSource $lastImportSource;
    private \DateTimeImmutable $lastImportedAt;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;
    private string $contentHash = '';

    /** @var list<IntegrationEventInterface> */
    private array $pendingIntegrationEvents = [];

    private function __construct()
    {
    }

    /**
     * @param JurisdictionalIdentifier[] $identifiers
     * @param Presentation[]             $initialPresentations
     * @param Composition[]              $compositions
     * @param TargetUsage[]              $targetUsages
     */
    public static function create(
        MarketingAuthorizationId $id,
        CommercialName $commercialName,
        HolderLaboratory $holderLaboratory,
        MarketingAuthorizationStatus $status,
        MarketingAuthorizationDate $authorizationDate,
        ProductNature $nature,
        PharmaceuticalForm $pharmaceuticalForm,
        ?AtcVetCode $atcVetCode,
        ?PermanentIdentifier $permanentIdentifier,
        ?ControlledSubstance $controlledSubstance,
        array $identifiers,
        array $initialPresentations,
        array $compositions,
        array $targetUsages,
        ?Summary $summary,
        ImportSource $source,
        \DateTimeImmutable $now,
    ): self {
        $ma                            = new self();
        $ma->id                        = $id;
        $ma->commercialName            = $commercialName;
        $ma->holderLaboratory          = $holderLaboratory;
        $ma->status                    = $status;
        $ma->authorizationDate         = $authorizationDate;
        $ma->nature                    = $nature;
        $ma->pharmaceuticalForm        = $pharmaceuticalForm;
        $ma->atcVetCode                = $atcVetCode;
        $ma->permanentIdentifier       = $permanentIdentifier;
        $ma->controlledSubstance       = $controlledSubstance;
        $ma->jurisdictionalIdentifiers = $identifiers;
        $ma->presentations             = $initialPresentations;
        $ma->compositions              = $compositions;
        $ma->targetUsages              = $targetUsages;
        $ma->summary                   = $summary;
        $ma->lastImportSource          = $source;
        $ma->lastImportedAt            = $now;
        $ma->createdAt                 = $now;
        $ma->updatedAt                 = $now;

        $ma->recordDomainEvent(new MarketingAuthorizationCreated(
            marketingAuthorizationId: $id->toString(),
            commercialName: $commercialName->toString(),
            source: $source->value,
        ));

        foreach ($initialPresentations as $presentation) {
            $ma->recordIntegrationEvent(new PresentationAdded(
                marketingAuthorizationId: $id->toString(),
                presentationId: $presentation->id()->toString(),
                gtin: $presentation->gtin()?->toString(),
            ));
        }

        return $ma;
    }

    /**
     * @param JurisdictionalIdentifier[] $jurisdictionalIdentifiers
     * @param Presentation[]             $presentations
     * @param Composition[]              $compositions
     * @param TargetUsage[]              $targetUsages
     */
    public static function reconstitute(
        MarketingAuthorizationId $id,
        CommercialName $commercialName,
        HolderLaboratory $holderLaboratory,
        MarketingAuthorizationStatus $status,
        MarketingAuthorizationDate $authorizationDate,
        ProductNature $nature,
        PharmaceuticalForm $pharmaceuticalForm,
        ?AtcVetCode $atcVetCode,
        ?PermanentIdentifier $permanentIdentifier,
        ?ControlledSubstance $controlledSubstance,
        array $jurisdictionalIdentifiers,
        array $presentations,
        array $compositions,
        array $targetUsages,
        ?Summary $summary,
        ImportSource $lastImportSource,
        \DateTimeImmutable $lastImportedAt,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
        string $contentHash = '',
    ): self {
        $ma                            = new self();
        $ma->id                        = $id;
        $ma->commercialName            = $commercialName;
        $ma->holderLaboratory          = $holderLaboratory;
        $ma->status                    = $status;
        $ma->authorizationDate         = $authorizationDate;
        $ma->nature                    = $nature;
        $ma->pharmaceuticalForm        = $pharmaceuticalForm;
        $ma->atcVetCode                = $atcVetCode;
        $ma->permanentIdentifier       = $permanentIdentifier;
        $ma->controlledSubstance       = $controlledSubstance;
        $ma->jurisdictionalIdentifiers = $jurisdictionalIdentifiers;
        $ma->presentations             = $presentations;
        $ma->compositions              = $compositions;
        $ma->targetUsages              = $targetUsages;
        $ma->summary                   = $summary;
        $ma->lastImportSource          = $lastImportSource;
        $ma->lastImportedAt            = $lastImportedAt;
        $ma->createdAt                 = $createdAt;
        $ma->updatedAt                 = $updatedAt;
        $ma->contentHash               = $contentHash;

        return $ma;
    }

    public function addJurisdictionalIdentifier(JurisdictionalIdentifier $identifier): void
    {
        foreach ($this->jurisdictionalIdentifiers as $existing) {
            if ($existing->equals($identifier)) {
                throw new DuplicateJurisdictionalIdException(
                    $identifier->jurisdictionCode()->toString(),
                    $identifier->authorityCode(),
                    $identifier->authorityIdentifier(),
                );
            }
        }

        $this->jurisdictionalIdentifiers[] = $identifier;
    }

    public function addPresentation(Presentation $presentation, \DateTimeImmutable $now): void
    {
        if (MarketingAuthorizationStatus::WITHDRAWN === $this->status) {
            throw new \DomainException('Cannot add a presentation to a withdrawn marketing authorization.');
        }

        $this->presentations[] = $presentation;
        $this->updatedAt       = $now;

        $this->recordIntegrationEvent(new PresentationAdded(
            marketingAuthorizationId: $this->id->toString(),
            presentationId: $presentation->id()->toString(),
            gtin: $presentation->gtin()?->toString(),
        ));
    }

    public function removePresentation(PresentationId $presentationId, \DateTimeImmutable $now): void
    {
        $this->presentations = array_values(array_filter(
            $this->presentations,
            static fn (Presentation $p) => !$p->id()->equals($presentationId),
        ));
        $this->updatedAt = $now;

        $this->recordIntegrationEvent(new PresentationRemoved(
            marketingAuthorizationId: $this->id->toString(),
            presentationId: $presentationId->toString(),
        ));
    }

    public function updatePresentation(
        PresentationId $presentationId,
        PresentationDescription $description,
        ?Gtin $gtin,
        PrescriptionRequirement $prescriptionRequirement,
        \DateTimeImmutable $now,
    ): void {
        foreach ($this->presentations as $key => $presentation) {
            if (!$presentation->id()->equals($presentationId)) {
                continue;
            }

            $previousGtin = $presentation->gtin();
            $gtinChanged  = (null === $previousGtin && null !== $gtin)
                || (null !== $previousGtin && null === $gtin)
                || (null !== $previousGtin && null !== $gtin && !$previousGtin->equals($gtin));

            $this->presentations[$key] = $presentation
                ->withDescription($description)
                ->withGtin($gtin)
                ->withPrescriptionRequirement($prescriptionRequirement)
            ;

            $this->updatedAt = $now;

            if ($gtinChanged) {
                $this->recordIntegrationEvent(new PresentationGtinChanged(
                    presentationId: $presentationId->toString(),
                    previousGtin: $previousGtin?->toString(),
                    newGtin: $gtin?->toString(),
                ));
            }

            return;
        }
    }

    public function withdraw(\DateTimeImmutable $now): void
    {
        if (MarketingAuthorizationStatus::WITHDRAWN === $this->status) {
            return;
        }

        $previous        = $this->status;
        $this->status    = MarketingAuthorizationStatus::WITHDRAWN;
        $this->updatedAt = $now;

        $this->recordIntegrationEvent(new MarketingAuthorizationWithdrawn(
            marketingAuthorizationId: $this->id->toString(),
            effectiveDate: $now->format('Y-m-d'),
        ));

        $this->recordIntegrationEvent(new MarketingAuthorizationStatusChanged(
            marketingAuthorizationId: $this->id->toString(),
            previousStatus: $previous->value,
            newStatus: $this->status->value,
        ));
    }

    public function suspend(\DateTimeImmutable $now): void
    {
        $previous        = $this->status;
        $this->status    = MarketingAuthorizationStatus::SUSPENDED;
        $this->updatedAt = $now;

        $this->recordIntegrationEvent(new MarketingAuthorizationStatusChanged(
            marketingAuthorizationId: $this->id->toString(),
            previousStatus: $previous->value,
            newStatus: $this->status->value,
        ));

        $this->recordIntegrationEvent(new MarketingAuthorizationSuspended(
            marketingAuthorizationId: $this->id->toString(),
        ));
    }

    public function reinstate(\DateTimeImmutable $now): void
    {
        $previous        = $this->status;
        $this->status    = MarketingAuthorizationStatus::ACTIVE;
        $this->updatedAt = $now;

        $this->recordIntegrationEvent(new MarketingAuthorizationStatusChanged(
            marketingAuthorizationId: $this->id->toString(),
            previousStatus: $previous->value,
            newStatus: $this->status->value,
        ));
    }

    public function updateControlledSubstance(?ControlledSubstance $controlledSubstance, \DateTimeImmutable $now): void
    {
        $changed = (null === $this->controlledSubstance && null !== $controlledSubstance)
            || (null !== $this->controlledSubstance && null === $controlledSubstance)
            || (
                null !== $this->controlledSubstance
                && null !== $controlledSubstance
                && !$this->controlledSubstance->equals($controlledSubstance)
            );

        if (!$changed) {
            return;
        }

        $this->controlledSubstance = $controlledSubstance;
        $this->updatedAt           = $now;

        $this->recordIntegrationEvent(new ControlledSubstanceClassificationChanged(
            marketingAuthorizationId: $this->id->toString(),
            newClass: $controlledSubstance?->class()->value,
        ));
    }

    /**
     * @param TargetUsage[] $targetUsages
     */
    public function updateTargetUsages(array $targetUsages, \DateTimeImmutable $now): void
    {
        $existingMap = [];

        foreach ($this->targetUsages as $usage) {
            $key               = $usage->targetSpeciesCode()->toString() . '|' . $usage->administrationRoute()->value;
            $existingMap[$key] = $usage;
        }

        $newMap = [];

        foreach ($targetUsages as $usage) {
            $key          = $usage->targetSpeciesCode()->toString() . '|' . $usage->administrationRoute()->value;
            $newMap[$key] = $usage;
        }

        foreach ($newMap as $key => $newUsage) {
            if (!isset($existingMap[$key])) {
                $this->recordDomainEvent(new TargetSpeciesAdded(
                    marketingAuthorizationId: $this->id->toString(),
                    speciesCode: $newUsage->targetSpeciesCode()->toString(),
                    route: $newUsage->administrationRoute()->value,
                ));
            } elseif (null !== $newUsage->withdrawalPeriod() || null !== $existingMap[$key]->withdrawalPeriod()) {
                $existingPeriod = $existingMap[$key]->withdrawalPeriod();
                $newPeriod      = $newUsage->withdrawalPeriod();

                $changed = (null === $existingPeriod && null !== $newPeriod)
                    || (null !== $existingPeriod && null === $newPeriod)
                    || (null !== $existingPeriod && null !== $newPeriod && !$existingPeriod->equals($newPeriod));

                if ($changed) {
                    $this->recordIntegrationEvent(new WithdrawalPeriodChanged(
                        marketingAuthorizationId: $this->id->toString(),
                        targetSpeciesCode: $newUsage->targetSpeciesCode()->toString(),
                        route: $newUsage->administrationRoute()->value,
                        newPeriod: $newPeriod?->toString(),
                    ));
                }
            }
        }

        $this->targetUsages = $targetUsages;
        $this->updatedAt    = $now;
    }

    public function updatePrescriptionOn(
        PresentationId $presentationId,
        PrescriptionRequirement $requirement,
        \DateTimeImmutable $now,
    ): void {
        foreach ($this->presentations as $key => $presentation) {
            if (!$presentation->id()->equals($presentationId)) {
                continue;
            }

            if ($presentation->prescriptionRequirement()->equals($requirement)) {
                return;
            }

            $this->presentations[$key] = $presentation->withPrescriptionRequirement($requirement);
            $this->updatedAt           = $now;

            $this->recordIntegrationEvent(new PrescriptionRequirementChanged(
                presentationId: $presentationId->toString(),
                marketingAuthorizationId: $this->id->toString(),
                newClass: $requirement->prescriptionClass()->value,
            ));

            return;
        }
    }

    public function updateFromImport(
        MarketingAuthorizationBlueprint $blueprint,
        ImportSource $source,
        \DateTimeImmutable $now,
    ): void {
        $changedFields = [];

        if (!$this->commercialName->equals($blueprint->commercialName)) {
            $this->commercialName = $blueprint->commercialName;
            $changedFields[]      = 'commercialName';
        }

        if (!$this->holderLaboratory->equals($blueprint->holderLaboratory)) {
            $this->holderLaboratory = $blueprint->holderLaboratory;
            $changedFields[]        = 'holderLaboratory';
        }

        if ($this->status !== $blueprint->status) {
            $previous        = $this->status;
            $this->status    = $blueprint->status;
            $changedFields[] = 'status';

            if (MarketingAuthorizationStatus::WITHDRAWN === $blueprint->status) {
                $this->recordIntegrationEvent(new MarketingAuthorizationWithdrawn(
                    marketingAuthorizationId: $this->id->toString(),
                    effectiveDate: $now->format('Y-m-d'),
                ));
            }

            $this->recordIntegrationEvent(new MarketingAuthorizationStatusChanged(
                marketingAuthorizationId: $this->id->toString(),
                previousStatus: $previous->value,
                newStatus: $blueprint->status->value,
            ));
        }

        if (!$this->pharmaceuticalForm->equals($blueprint->pharmaceuticalForm)) {
            $this->pharmaceuticalForm = $blueprint->pharmaceuticalForm;
            $changedFields[]          = 'pharmaceuticalForm';
        }

        $this->updateControlledSubstance($blueprint->controlledSubstance, $now);

        $this->lastImportSource = $source;
        $this->lastImportedAt   = $now;
        $this->updatedAt        = $now;

        if ([] !== $changedFields) {
            $this->recordIntegrationEvent(new MarketingAuthorizationUpdated(
                marketingAuthorizationId: $this->id->toString(),
                changedFields: $changedFields,
            ));
        }
    }

    public function id(): MarketingAuthorizationId
    {
        return $this->id;
    }

    public function commercialName(): CommercialName
    {
        return $this->commercialName;
    }

    public function holderLaboratory(): HolderLaboratory
    {
        return $this->holderLaboratory;
    }

    public function status(): MarketingAuthorizationStatus
    {
        return $this->status;
    }

    public function authorizationDate(): MarketingAuthorizationDate
    {
        return $this->authorizationDate;
    }

    public function nature(): ProductNature
    {
        return $this->nature;
    }

    public function pharmaceuticalForm(): PharmaceuticalForm
    {
        return $this->pharmaceuticalForm;
    }

    public function atcVetCode(): ?AtcVetCode
    {
        return $this->atcVetCode;
    }

    public function permanentIdentifier(): ?PermanentIdentifier
    {
        return $this->permanentIdentifier;
    }

    public function controlledSubstance(): ?ControlledSubstance
    {
        return $this->controlledSubstance;
    }

    /** @return JurisdictionalIdentifier[] */
    public function jurisdictionalIdentifiers(): array
    {
        return $this->jurisdictionalIdentifiers;
    }

    /** @return Presentation[] */
    public function presentations(): array
    {
        return $this->presentations;
    }

    /** @return Composition[] */
    public function compositions(): array
    {
        return $this->compositions;
    }

    /** @return TargetUsage[] */
    public function targetUsages(): array
    {
        return $this->targetUsages;
    }

    public function summary(): ?Summary
    {
        return $this->summary;
    }

    public function lastImportSource(): ImportSource
    {
        return $this->lastImportSource;
    }

    public function lastImportedAt(): \DateTimeImmutable
    {
        return $this->lastImportedAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function contentHash(): string
    {
        return $this->contentHash;
    }

    public function withContentHash(string $contentHash): static
    {
        $clone              = clone $this;
        $clone->contentHash = $contentHash;

        return $clone;
    }

    /**
     * Pulls and clears pending integration events for cross-BC publishing.
     *
     * @return list<IntegrationEventInterface>
     */
    #[\NoDiscard]
    public function pullIntegrationEvents(): array
    {
        $events                         = $this->pendingIntegrationEvents;
        $this->pendingIntegrationEvents = [];

        return $events;
    }

    private function recordIntegrationEvent(IntegrationEventInterface $event): void
    {
        $this->pendingIntegrationEvents[] = $event;
    }
}
