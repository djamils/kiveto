<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\Console;

use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\PharmaceuticalRegistry\Application\Command\CalculateSnapshotDiff\CalculateSnapshotDiff;
use App\System\PharmaceuticalRegistry\Application\Command\CalculateSnapshotDiff\CalculateSnapshotDiffHandler;
use App\System\PharmaceuticalRegistry\Application\Command\ImportSnapshot\ImportSnapshot;
use App\System\PharmaceuticalRegistry\Application\Command\ImportSnapshot\ImportSnapshotHandler;
use App\System\PharmaceuticalRegistry\Application\Port\BlueprintBuilderInterface;
use App\System\PharmaceuticalRegistry\Domain\ActiveSubstance;
use App\System\PharmaceuticalRegistry\Domain\Entity\Composition;
use App\System\PharmaceuticalRegistry\Domain\Entity\Presentation;
use App\System\PharmaceuticalRegistry\Domain\MarketingAuthorization;
use App\System\PharmaceuticalRegistry\Domain\Repository\ActiveSubstanceRepositoryInterface;
use App\System\PharmaceuticalRegistry\Domain\Repository\MarketingAuthorizationRepositoryInterface;
use App\System\PharmaceuticalRegistry\Domain\Repository\SnapshotRepositoryInterface;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ActiveSubstanceId;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\DiffKind;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ImportResult;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ImportSource;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\PresentationId;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\SnapshotId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Dedicated bootstrap command for the initial full import of ~14 000 products.
 * Bypasses the Messenger doctrine_transaction middleware intentionally — manages transactions per batch.
 * Do NOT dispatch this via the command bus — inject directly as a service.
 */
#[AsCommand(name: 'app:pharmaceutical-registry:bootstrap', description: 'Initial bootstrap import of ANMV data (batch-transacted)')]
final class BootstrapAnmvImportCommand extends Command
{
    public function __construct(
        private readonly ImportSnapshotHandler $importSnapshotHandler,
        private readonly CalculateSnapshotDiffHandler $calculateSnapshotDiffHandler,
        private readonly MarketingAuthorizationRepositoryInterface $authorizationRepository,
        private readonly ActiveSubstanceRepositoryInterface $activeSubstanceRepository,
        private readonly SnapshotRepositoryInterface $snapshotRepository,
        private readonly BlueprintBuilderInterface $blueprintBuilder,
        private readonly UuidGeneratorInterface $uuidGenerator,
        private readonly ClockInterface $clock,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Path to the ANMV XML file')
            ->addOption('dictionary', null, InputOption::VALUE_REQUIRED, 'Path to the ANMV dictionary XML file')
            ->addOption('batch', null, InputOption::VALUE_OPTIONAL, 'Batch size', 500)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $file */
        $file = $input->getOption('file');
        /** @var string $dictionary */
        $dictionary = $input->getOption('dictionary');
        /** @var int|string $batchRaw */
        $batchRaw  = $input->getOption('batch');
        $batchSize = (int) $batchRaw;

        $output->writeln('<info>Staging ANMV snapshot...</info>');

        $snapshotId = ($this->importSnapshotHandler)(new ImportSnapshot(
            source: ImportSource::ANMV->value,
            filePath: $file,
            dictionaryPath: $dictionary,
        ));

        $output->writeln(\sprintf('<info>Snapshot staged: %s. Calculating diff...</info>', $snapshotId));

        ($this->calculateSnapshotDiffHandler)(new CalculateSnapshotDiff(snapshotId: $snapshotId));

        $output->writeln('<info>Diff calculated. Applying in batches...</info>');

        $snapshot = $this->snapshotRepository->findById(SnapshotId::fromString($snapshotId));

        if (null === $snapshot) {
            $output->writeln('<error>Snapshot not found after staging.</error>');

            return Command::FAILURE;
        }

        $now       = $this->clock->now();
        $created   = 0;
        $updated   = 0;
        $withdrawn = 0;
        $skipped   = 0;
        $batch     = [];
        $batchNum  = 0;

        try {
            foreach ($snapshot->diffEntries() as $entry) {
                $batch[] = $entry;

                if (\count($batch) >= $batchSize) {
                    $this->processBatch($batch, $snapshot->source()->value, $now, $created, $updated, $withdrawn, $skipped);
                    $batch = [];
                    ++$batchNum;
                    $output->writeln(\sprintf('<comment>Batch %d processed (created: %d, updated: %d, withdrawn: %d)</comment>', $batchNum, $created, $updated, $withdrawn));
                }
            }

            if ([] !== $batch) {
                $this->processBatch($batch, $snapshot->source()->value, $now, $created, $updated, $withdrawn, $skipped);
            }

            $snapshot->markAsApplied(ImportResult::of($created, $updated, $withdrawn, $skipped), $now);
            $this->snapshotRepository->save($snapshot);
        } catch (\Throwable $e) {
            $snapshot->markAsFailed($e->getMessage(), $now);
            $this->snapshotRepository->save($snapshot);
            $output->writeln(\sprintf('<error>Bootstrap failed: %s</error>', $e->getMessage()));

            return Command::FAILURE;
        }

        $output->writeln(\sprintf(
            '<info>Bootstrap complete — created: %d, updated: %d, withdrawn: %d, skipped: %d</info>',
            $created,
            $updated,
            $withdrawn,
            $skipped,
        ));

        return Command::SUCCESS;
    }

    /**
     * @param \App\System\PharmaceuticalRegistry\Domain\Entity\DiffEntry[] $batch
     */
    private function processBatch(
        array $batch,
        string $source,
        \DateTimeImmutable $now,
        int &$created,
        int &$updated,
        int &$withdrawn,
        int &$skipped,
    ): void {
        $this->em->beginTransaction();

        try {
            $importSource = ImportSource::from($source);

            foreach ($batch as $entry) {
                match ($entry->diffKind()) {
                    DiffKind::CREATE   => $this->applyCreate($entry, $importSource, $now, $created),
                    DiffKind::UPDATE   => $this->applyUpdate($entry, $importSource, $now, $updated),
                    DiffKind::WITHDRAW => $this->applyWithdraw($entry, $now, $withdrawn),
                };
            }

            $this->em->flush();
            $this->em->commit();
            $this->em->clear();
        } catch (\Throwable $e) {
            $this->em->rollback();

            throw $e;
        }
    }

    private function applyCreate(
        \App\System\PharmaceuticalRegistry\Domain\Entity\DiffEntry $entry,
        ImportSource $source,
        \DateTimeImmutable $now,
        int &$created,
    ): void {
        $rawDto    = $entry->rawDto() ?? [];
        $blueprint = $this->blueprintBuilder->buildBlueprint($rawDto, $source);

        $compositions = [];

        foreach ($blueprint->compositions as $compositionBlueprint) {
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
        ++$created;
    }

    private function applyUpdate(
        \App\System\PharmaceuticalRegistry\Domain\Entity\DiffEntry $entry,
        ImportSource $source,
        \DateTimeImmutable $now,
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
        $blueprint = $this->blueprintBuilder->buildBlueprint($rawDto, $source);
        $ma->updateFromImport($blueprint, $blueprint->source, $now);
        $ma = $ma->withContentHash($blueprint->contentHash->toString());
        $this->authorizationRepository->save($ma);
        ++$updated;
    }

    private function applyWithdraw(
        \App\System\PharmaceuticalRegistry\Domain\Entity\DiffEntry $entry,
        \DateTimeImmutable $now,
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
        ++$withdrawn;
    }
}
