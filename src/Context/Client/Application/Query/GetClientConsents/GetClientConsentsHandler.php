<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\GetClientConsents;

use App\Context\Client\Application\Port\ClientConsentReaderInterface;
use App\Context\Client\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetClientConsentsHandler
{
    public function __construct(
        private ClientConsentReaderInterface $reader,
    ) {
    }

    /**
     * @return array<string, ClientConsentsView>
     */
    public function __invoke(GetClientConsents $query): array
    {
        if ([] === $query->clientIds) {
            return [];
        }

        return $this->reader->consentsOf(ClinicId::fromString($query->clinicId), array_values(array_unique($query->clientIds)));
    }
}
