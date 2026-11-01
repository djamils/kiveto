<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListDataExports;

use App\Context\ClientPortal\Domain\Export\DataExport;
use App\Context\ClientPortal\Domain\Export\DataExportRepositoryInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListDataExportsHandler
{
    public function __construct(
        private DataExportRepositoryInterface $exports,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return list<DataExportSummary>
     */
    public function __invoke(ListDataExports $query): array
    {
        $now = $this->clock->now();

        return array_map(static fn (DataExport $export): DataExportSummary => new DataExportSummary(
            $export->id()->toString(),
            $export->statusAt($now)->value,
            $export->requestedAt(),
            $export->readyAt(),
            $export->expiresAt(),
        ), $this->exports->findByUser($query->userId));
    }
}
