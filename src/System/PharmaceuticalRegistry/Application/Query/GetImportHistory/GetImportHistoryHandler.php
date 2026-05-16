<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Query\GetImportHistory;

use App\System\PharmaceuticalRegistry\Domain\Repository\SnapshotRepositoryInterface;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ImportSource;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetImportHistoryHandler
{
    public function __construct(
        private SnapshotRepositoryInterface $snapshotRepository,
    ) {
    }

    /**
     * @return ImportHistoryView[]
     */
    public function __invoke(GetImportHistory $query): array
    {
        $source    = null !== $query->source ? ImportSource::from($query->source) : ImportSource::ANMV;
        $snapshots = $this->snapshotRepository->findRecent($source, $query->limit);

        return array_map(
            static fn ($snapshot) => new ImportHistoryView(
                id: $snapshot->id()->toString(),
                source: $snapshot->source()->value,
                status: $snapshot->status()->value,
                downloadedAt: $snapshot->downloadedAt(),
                appliedAt: $snapshot->appliedAt(),
                errorMessage: $snapshot->errorMessage(),
            ),
            $snapshots,
        );
    }
}
