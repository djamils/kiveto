<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Command\CalculateSnapshotDiff;

use App\System\PharmaceuticalRegistry\Domain\Exception\SnapshotNotFoundException;
use App\System\PharmaceuticalRegistry\Domain\Repository\MarketingAuthorizationRepositoryInterface;
use App\System\PharmaceuticalRegistry\Domain\Repository\SnapshotRepositoryInterface;
use App\System\PharmaceuticalRegistry\Domain\Service\RegistryDiffCalculator;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\SnapshotId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CalculateSnapshotDiffHandler
{
    public function __construct(
        private readonly SnapshotRepositoryInterface $snapshotRepository,
        private readonly MarketingAuthorizationRepositoryInterface $authorizationRepository,
        private readonly RegistryDiffCalculator $diffCalculator,
    ) {
    }

    public function __invoke(CalculateSnapshotDiff $command): void
    {
        $snapshotId = SnapshotId::fromString($command->snapshotId);
        $snapshot   = $this->snapshotRepository->findById($snapshotId);

        if (null === $snapshot) {
            throw new SnapshotNotFoundException($command->snapshotId);
        }

        $projections     = $this->authorizationRepository->findProjectionsBySource($snapshot->source());
        $snapshotEntries = $this->snapshotRepository->streamEntriesForDiff($snapshotId);

        $diffEntries = $this->diffCalculator->calculate($snapshotEntries, $projections);

        $snapshot->markAsDiffed($diffEntries);
        $this->snapshotRepository->save($snapshot);
    }
}
