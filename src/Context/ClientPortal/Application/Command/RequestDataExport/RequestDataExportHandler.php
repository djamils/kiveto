<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\RequestDataExport;

use App\Context\ClientPortal\Application\Port\DataExportJobsInterface;
use App\Context\ClientPortal\Application\Port\DataExportQuotaInterface;
use App\Context\ClientPortal\Domain\Exception\DataExportRateLimitedException;
use App\Context\ClientPortal\Domain\Export\DataExport;
use App\Context\ClientPortal\Domain\Export\DataExportId;
use App\Context\ClientPortal\Domain\Export\DataExportRepositoryInterface;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The quota is consumed first, outside the transaction (its own
 * connection): an accepted request counts even if what follows fails.
 *
 * @throws DataExportRateLimitedException
 */
#[AsMessageHandler]
final readonly class RequestDataExportHandler
{
    public function __construct(
        private DataExportQuotaInterface $quota,
        private DataExportRepositoryInterface $exports,
        private DataExportJobsInterface $jobs,
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RequestDataExport $command): string
    {
        $this->quota->consume($command->userId);

        $export = DataExport::request(DataExportId::fromString($this->uuidGenerator->generate()), $command->userId, $this->clock->now());
        $this->exports->add($export);
        $this->jobs->queueBuild($export->id()->toString());

        return $export->id()->toString();
    }
}
