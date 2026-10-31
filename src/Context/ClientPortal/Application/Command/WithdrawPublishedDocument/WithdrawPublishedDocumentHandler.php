<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\WithdrawPublishedDocument;

use App\Context\ClientPortal\Domain\Document\PublishedDocument;
use App\Context\ClientPortal\Domain\Document\PublishedDocumentId;
use App\Context\ClientPortal\Domain\Document\PublishedDocumentRepositoryInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * A withdrawal delivered before its publication leaves a tombstone, so
 * that the late publication finds the key taken and publishes nothing.
 */
#[AsMessageHandler]
final readonly class WithdrawPublishedDocumentHandler
{
    public function __construct(
        private PublishedDocumentRepositoryInterface $documents,
        private UuidGeneratorInterface $uuids,
        private ClockInterface $clock,
        private DomainEventPublisher $eventPublisher,
    ) {
    }

    public function __invoke(WithdrawPublishedDocument $command): void
    {
        $now      = $this->clock->now();
        $document = $this->documents->findForUpdate($command->clinicId, $command->type, $command->sourceRef);

        if (null === $document) {
            $tombstone = PublishedDocument::tombstone(
                PublishedDocumentId::fromString($this->uuids->generate()),
                $command->clinicId,
                $command->type,
                $command->sourceRef,
                $now,
            );

            if ($this->documents->insertIfAbsent($tombstone)) {
                return;
            }

            // Published meanwhile: withdraw that row.
            $document = $this->documents->findForUpdate($command->clinicId, $command->type, $command->sourceRef)
                ?? throw new \LogicException('The published document row vanished.');
        }

        $document->withdraw($now);
        $this->documents->save($document);
        $this->eventPublisher->publish($document);
    }
}
