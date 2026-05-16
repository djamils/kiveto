<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Command\ApplySnapshotDiff;

use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Application\Event\IntegrationEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\PharmaceuticalRegistry\Application\Port\BlueprintBuilderInterface;
use App\System\PharmaceuticalRegistry\Domain\ActiveSubstance;
use App\System\PharmaceuticalRegistry\Domain\Entity\Composition;
use App\System\PharmaceuticalRegistry\Domain\Entity\DiffEntry;
use App\System\PharmaceuticalRegistry\Domain\Entity\Presentation;
use App\System\PharmaceuticalRegistry\Domain\Exception\SnapshotNotFoundException;
use App\System\PharmaceuticalRegistry\Domain\MarketingAuthorization;
use App\System\PharmaceuticalRegistry\Domain\Repository\ActiveSubstanceRepositoryInterface;
use App\System\PharmaceuticalRegistry\Domain\Repository\MarketingAuthorizationRepositoryInterface;
use App\System\PharmaceuticalRegistry\Domain\Repository\SnapshotRepositoryInterface;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ActiveSubstanceId;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\DiffKind;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ImportResult;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\PresentationId;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\SnapshotId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ApplySnapshotDiffHandler
{
    public function __construct(
        private readonly MarketingAuthorizationRepositoryInterface $authorizationRepository,
        private readonly ActiveSubstanceRepositoryInterface $activeSubstanceRepository,
        private readonly SnapshotRepositoryInterface $snapshotRepository,
        private readonly BlueprintBuilderInterface $blueprintBuilder,
        private readonly DomainEventPublisher $domainEventPublisher,
        private readonly IntegrationEventPublisher $integrationEventPublisher,
        private readonly UuidGeneratorInterface $uuidGenerator,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(ApplySnapshotDiff $command): void
    {
        $snapshotId = SnapshotId::fromString($command->snapshotId);
        $snapshot   = $this->snapshotRepository->findById($snapshotId);

        if (null === $snapshot) {
            throw new SnapshotNotFoundException($command->snapshotId);
        }

        $now               = $this->clock->now();
        $created           = 0;
        $updated           = 0;
        $withdrawn         = 0;
        $skipped           = 0;
        $mutatedAggregates = [];

        foreach ($snapshot->diffEntries() as $entry) {
            match ($entry->diffKind()) {
                DiffKind::CREATE   => $this->handleCreate($entry, $snapshot->source()->value, $now, $mutatedAggregates, $created),
                DiffKind::UPDATE   => $this->handleUpdate($entry, $snapshot->source()->value, $now, $mutatedAggregates, $updated),
                DiffKind::WITHDRAW => $this->handleWithdraw($entry, $now, $mutatedAggregates, $withdrawn),
            };
        }

        $snapshot->markAsApplied(ImportResult::of($created, $updated, $withdrawn, $skipped), $now);
        $this->snapshotRepository->save($snapshot);

        foreach ($mutatedAggregates as $aggregate) {
            $this->domainEventPublisher->publish($aggregate);

            $integrationEvents = $aggregate->pullIntegrationEvents();

            if ([] !== $integrationEvents) {
                $this->integrationEventPublisher->publish(...$integrationEvents);
            }
        }
    }

    /**
     * @param MarketingAuthorization[] $mutatedAggregates
     */
    private function handleCreate(
        DiffEntry $entry,
        string $source,
        \DateTimeImmutable $now,
        array &$mutatedAggregates,
        int &$created,
    ): void {
        $rawDto    = $entry->rawDto() ?? [];
        $blueprint = $this->blueprintBuilder->buildBlueprint($rawDto, \App\System\PharmaceuticalRegistry\Domain\ValueObject\ImportSource::from($source));

        $compositions = $this->resolveCompositions($blueprint->compositions, $now);

        $presentations = [];

        foreach ($blueprint->presentations as $presentationBlueprint) {
            $presentations[] = Presentation::create(
                id: PresentationId::fromString($this->uuidGenerator->generate()),
                description: $presentationBlueprint->description,
                unitCount: $presentationBlueprint->unitCount,
                packaging: $presentationBlueprint->packaging,
                gtin: $presentationBlueprint->gtin,
                euPackIdentifier: $presentationBlueprint->euPackIdentifier,
                prescriptionRequirement: $presentationBlueprint->prescriptionRequirement,
            );
        }

        $ma = MarketingAuthorization::create(
            id: \App\System\PharmaceuticalRegistry\Domain\ValueObject\MarketingAuthorizationId::fromString($this->uuidGenerator->generate()),
            commercialName: $blueprint->commercialName,
            holderLaboratory: $blueprint->holderLaboratory,
            status: $blueprint->status,
            authorizationDate: $blueprint->authorizationDate,
            nature: $blueprint->nature,
            pharmaceuticalForm: $blueprint->pharmaceuticalForm,
            atcVetCode: $blueprint->atcVetCode,
            permanentIdentifier: $blueprint->permanentIdentifier,
            controlledSubstance: $blueprint->controlledSubstance,
            identifiers: $blueprint->jurisdictionalIdentifiers,
            initialPresentations: $presentations,
            compositions: $compositions,
            targetUsages: $blueprint->targetUsages,
            summary: $blueprint->summary,
            source: $blueprint->source,
            now: $now,
        );

        $ma = $ma->withContentHash($blueprint->contentHash->toString());
        $this->authorizationRepository->save($ma);
        $mutatedAggregates[] = $ma;
        ++$created;
    }

    /**
     * @param MarketingAuthorization[] $mutatedAggregates
     */
    private function handleUpdate(
        DiffEntry $entry,
        string $source,
        \DateTimeImmutable $now,
        array &$mutatedAggregates,
        int &$updated,
    ): void {
        if (null === $entry->targetUuid()) {
            return;
        }

        $ma = $this->authorizationRepository->findById($entry->targetUuid());

        if (null === $ma) {
            return;
        }

        $rawDto    = $entry->rawDto() ?? [];
        $blueprint = $this->blueprintBuilder->buildBlueprint($rawDto, \App\System\PharmaceuticalRegistry\Domain\ValueObject\ImportSource::from($source));

        $ma->updateFromImport($blueprint, $blueprint->source, $now);
        $this->authorizationRepository->save($ma);
        $mutatedAggregates[] = $ma;
        ++$updated;
    }

    /**
     * @param MarketingAuthorization[] $mutatedAggregates
     */
    private function handleWithdraw(
        DiffEntry $entry,
        \DateTimeImmutable $now,
        array &$mutatedAggregates,
        int &$withdrawn,
    ): void {
        if (null === $entry->targetUuid()) {
            return;
        }

        $ma = $this->authorizationRepository->findById($entry->targetUuid());

        if (null === $ma) {
            return;
        }

        $ma->withdraw($now);
        $this->authorizationRepository->save($ma);
        $mutatedAggregates[] = $ma;
        ++$withdrawn;
    }

    /**
     * @param \App\System\PharmaceuticalRegistry\Domain\CompositionBlueprint[] $compositionBlueprints
     *
     * @return Composition[]
     */
    private function resolveCompositions(array $compositionBlueprints, \DateTimeImmutable $now): array
    {
        $compositions = [];

        foreach ($compositionBlueprints as $compositionBlueprint) {
            $normalizedLabel = ActiveSubstance::normalizeLabel($compositionBlueprint->activeSubstanceLabel);
            $substance       = $this->activeSubstanceRepository->findByNormalizedLabel($normalizedLabel);

            if (null === $substance) {
                $substance = ActiveSubstance::create(
                    id: ActiveSubstanceId::fromString($this->uuidGenerator->generate()),
                    label: $compositionBlueprint->activeSubstanceLabel,
                    innCode: null,
                    now: $now,
                );
                $this->activeSubstanceRepository->save($substance);
            }

            $compositions[] = Composition::of(
                activeSubstanceId: $substance->id(),
                quantityValue: $compositionBlueprint->quantityValue,
                quantityUnitLabel: $compositionBlueprint->quantityUnitLabel,
                quantityUnitCode: $compositionBlueprint->quantityUnitCode,
                isExcipient: $compositionBlueprint->isExcipient,
            );
        }

        return $compositions;
    }
}
