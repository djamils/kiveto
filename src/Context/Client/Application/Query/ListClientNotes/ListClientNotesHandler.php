<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\ListClientNotes;

use App\Context\Client\Application\Port\ClientNoteReadRepositoryInterface;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListClientNotesHandler
{
    public function __construct(
        private ClientNoteReadRepositoryInterface $readRepository,
    ) {
    }

    /**
     * @return list<ClientNoteView>
     */
    public function __invoke(ListClientNotes $query): array
    {
        return $this->readRepository->listForClient(
            ClinicId::fromString($query->clinicId),
            ClientId::fromString($query->clientId),
        );
    }
}
