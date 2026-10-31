<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\MarkDocumentRead;

use App\Context\ClientPortal\Domain\Document\DocumentReadRepositoryInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class MarkDocumentReadHandler
{
    public function __construct(
        private DocumentReadRepositoryInterface $reads,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(MarkDocumentRead $command): void
    {
        $this->reads->markRead($command->documentId, $command->userId, $this->clock->now());
    }
}
