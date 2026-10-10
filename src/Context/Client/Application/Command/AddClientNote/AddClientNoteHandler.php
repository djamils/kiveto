<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Command\AddClientNote;

use App\Context\Client\Domain\ClientNote;
use App\Context\Client\Domain\Repository\ClientNoteRepositoryInterface;
use App\Context\Client\Domain\Repository\ClientRepositoryInterface;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClientNoteId;
use App\Context\Client\Domain\ValueObject\ClientNoteKind;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AddClientNoteHandler
{
    public function __construct(
        private ClientRepositoryInterface $clientRepository,
        private ClientNoteRepositoryInterface $noteRepository,
        private UuidGeneratorInterface $uuidGenerator,
        private DomainEventPublisher $domainEventPublisher,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(AddClientNote $command): string
    {
        $clinicId = ClinicId::fromString($command->clinicId);
        $clientId = ClientId::fromString($command->clientId);

        // Throws when the client does not belong to the clinic.
        $this->clientRepository->get($clinicId, $clientId);

        $noteId = ClientNoteId::fromString($this->uuidGenerator->generate());

        $note = ClientNote::add(
            id: $noteId,
            clinicId: $clinicId,
            clientId: $clientId,
            kind: ClientNoteKind::from($command->kind),
            content: $command->content,
            authorName: $command->authorName,
            createdAt: $this->clock->now(),
        );

        $this->noteRepository->save($note);
        $this->domainEventPublisher->publish($note);

        return $noteId->toString();
    }
}
