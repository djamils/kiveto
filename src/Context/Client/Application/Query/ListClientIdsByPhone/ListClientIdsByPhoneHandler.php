<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\ListClientIdsByPhone;

use App\Context\Client\Application\Port\ClientReadRepositoryInterface;
use App\Context\Client\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListClientIdsByPhoneHandler
{
    public function __construct(
        private ClientReadRepositoryInterface $clientReadRepository,
    ) {
    }

    /**
     * @return list<string>
     */
    public function __invoke(ListClientIdsByPhone $query): array
    {
        $phone = trim($query->phone);

        if ('' === $phone) {
            return [];
        }

        return $this->clientReadRepository->findIdsByPhone(ClinicId::fromString($query->clinicId), $phone);
    }
}
