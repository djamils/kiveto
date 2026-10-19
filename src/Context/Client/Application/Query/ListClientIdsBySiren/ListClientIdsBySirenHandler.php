<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\ListClientIdsBySiren;

use App\Context\Client\Application\Port\ClientReadRepositoryInterface;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Shared\Domain\ValueObject\InvalidSirenException;
use App\Shared\Domain\ValueObject\Siren;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListClientIdsBySirenHandler
{
    public function __construct(
        private ClientReadRepositoryInterface $clientReadRepository,
    ) {
    }

    /**
     * @return list<string>
     */
    public function __invoke(ListClientIdsBySiren $query): array
    {
        try {
            $siren = Siren::fromInput($query->siren);
        } catch (InvalidSirenException) {
            // An invalid SIREN cannot be on file.
            return [];
        }

        return $this->clientReadRepository->findIdsBySiren(ClinicId::fromString($query->clinicId), $siren->value());
    }
}
