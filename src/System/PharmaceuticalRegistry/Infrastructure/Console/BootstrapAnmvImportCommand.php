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
use App\System\PharmaceuticalRegistry\Domain\Entity\DiffEntry;
use App\System\PharmaceuticalRegistry\Domain\Entity\Presentation;
use App\System\PharmaceuticalRegistry\Domain\MarketingAuthorization;
use App\System\PharmaceuticalRegistry\Domain\Repository\ActiveSubstanceRepositoryInterface;
use App\System\PharmaceuticalRegistry\Domain\Repository\MarketingAuthorizationRepositoryInterface;
use App\System\PharmaceuticalRegistry\Domain\Repository\SnapshotRepositoryInterface;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ActiveSubstanceId;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\DiffKind;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ImportResult;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ImportSource;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\MarketingAuthorizationId;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\PresentationId;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\SnapshotId;
use App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity\SnapshotEntryEntity;
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
        private readonly ImportFileManager $importFileManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('file', null, InputOption::VALUE_OPTIONAL, '[debug] Override amm.xml path')
            ->addOption('dictionary', null, InputOption::VALUE_OPTIONAL, '[debug] Override dict.xml path')
            ->addOption('batch', null, InputOption::VALUE_OPTIONAL, 'Batch size', 500)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Bootstrap handles ~14 000 products — needs more than the default 128M
        ini_set('memory_limit', '1G');

        /** @var string|null $fileOverride */
        $fileOverride = $input->getOption('file');
        /** @var string|null $dictOverride */
        $dictOverride    = $input->getOption('dictionary');
        $isDebugOverride = null !== $fileOverride || null !== $dictOverride;

        $file       = $this->importFileManager->resolveFile(ImportSource::ANMV, 'amm.xml', $fileOverride);
        $dictionary = $this->importFileManager->resolveFile(ImportSource::ANMV, 'dict.xml', $dictOverride);

        /** @var int|string $batchRaw */
        $batchRaw  = $input->getOption('batch');
        $batchSize = (int) $batchRaw;

        if (!file_exists($file)) {
            $output->writeln(\sprintf('<error>File not found: %s</error>', $file));
            $output->writeln('<comment>Place amm.xml and dict.xml in storage/pharma-registry/france/current/ before importing.</comment>');

            return Command::FAILURE;
        }

        if (!file_exists($dictionary)) {
            $output->writeln(\sprintf('<error>Dictionary not found: %s</error>', $dictionary));

            return Command::FAILURE;
        }

        $output->writeln('<info>Staging ANMV snapshot...</info>');

        $snapshotId = ($this->importSnapshotHandler)(new ImportSnapshot(
            source: ImportSource::ANMV->value,
            filePath: $file,
            dictionaryPath: $dictionary,
        ));

        $output->writeln(\sprintf('<info>Snapshot staged: %s. Calculating diff...</info>', $snapshotId));

        ($this->calculateSnapshotDiffHandler)(new CalculateSnapshotDiff(snapshotId: $snapshotId));

        $output->writeln('<info>Diff calculated. Applying in batches...</info>');

        $snapshot = $this->snapshotRepository->findById(SnapshotId::fromString($snapshotId), withDiffEntries: false);

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

        // Stream diff entries directly from DB to avoid loading all rawDtos at once (memory).
        $snapshotUuidHex = str_replace('-', '', $snapshotId);
        $table           = $this->em->getClassMetadata(SnapshotEntryEntity::class)->getTableName();

        try {
            $stmt = $this->em->getConnection()->executeQuery(
                'SELECT authority_identifier, diff_kind, target_uuid, raw_dto'
                . " FROM {$table}"
                . ' WHERE snapshot_id = UNHEX(?) AND diff_kind IS NOT NULL',
                [$snapshotUuidHex],
            );

            foreach ($stmt->iterateAssociative() as $row) {
                \assert(\is_string($row['authority_identifier']));
                \assert(\is_string($row['diff_kind']));

                $targetUuid = null;

                if (null !== $row['target_uuid']) {
                    \assert(\is_string($row['target_uuid']));
                    $targetUuid = MarketingAuthorizationId::fromString(
                        \sprintf(
                            '%s-%s-%s-%s-%s',
                            bin2hex(substr($row['target_uuid'], 0, 4)),
                            bin2hex(substr($row['target_uuid'], 4, 2)),
                            bin2hex(substr($row['target_uuid'], 6, 2)),
                            bin2hex(substr($row['target_uuid'], 8, 2)),
                            bin2hex(substr($row['target_uuid'], 10, 6)),
                        ),
                    );
                }

                /** @var array<mixed>|null $rawDto */
                $rawDto = null !== $row['raw_dto'] && \is_string($row['raw_dto'])
                    ? json_decode($row['raw_dto'], true)
                    : null;

                $batch[] = new DiffEntry(
                    authorityIdentifier: $row['authority_identifier'],
                    diffKind: DiffKind::from($row['diff_kind']),
                    targetUuid: $targetUuid,
                    rawDto: $rawDto,
                );

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
            $output->writeln(\sprintf('<error>Bootstrap failed: %s in %s:%d</error>', $e->getMessage(), $e->getFile(), $e->getLine()));

            try {
                $snapshot->markAsFailed($e->getMessage(), $now);
                $this->snapshotRepository->save($snapshot);
            } catch (\Throwable) {
            }

            return Command::FAILURE;
        }

        $output->writeln(\sprintf(
            '<info>Bootstrap complete — created: %d, updated: %d, withdrawn: %d, skipped: %d</info>',
            $created,
            $updated,
            $withdrawn,
            $skipped,
        ));

        if (!$isDebugOverride) {
            $this->importFileManager->archiveCurrentFiles(ImportSource::ANMV, $now);
            $output->writeln('<info>Files archived.</info>');
        }

        return Command::SUCCESS;
    }

    /**
     * @param DiffEntry[] $batch
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
            gc_collect_cycles();
        } catch (\Throwable $e) {
            $this->em->rollback();

            throw $e;
        }
    }

    private function applyCreate(
        DiffEntry $entry,
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
            id: MarketingAuthorizationId::fromString($this->uuidGenerator->generate()),
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
        DiffEntry $entry,
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
        DiffEntry $entry,
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
