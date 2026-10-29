<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Command\RecordAutomatedCommunication;

use App\Context\Client\Domain\ClientCommunication;
use App\Context\Client\Domain\Exception\ClientNotFoundException;
use App\Context\Client\Domain\Repository\ClientCommunicationRepositoryInterface;
use App\Context\Client\Domain\Repository\ClientRepositoryInterface;
use App\Context\Client\Domain\ValueObject\ClientCommunicationId;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Domain\ValueObject\ContactChannel;
use App\Context\Client\Domain\ValueObject\DeliveryStatus;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Inserts the automated entry if absent (on the unique message id, so two
 * consumers never collide), else locks it and keeps the newest status.
 */
#[AsMessageHandler]
final readonly class RecordAutomatedCommunicationHandler
{
    public function __construct(
        private ClientRepositoryInterface $clientRepository,
        private ClientCommunicationRepositoryInterface $communicationRepository,
        private UuidGeneratorInterface $uuidGenerator,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(RecordAutomatedCommunication $command): void
    {
        $clinicId = ClinicId::fromString($command->clinicId);
        $clientId = ClientId::fromString($command->clientId);

        try {
            $this->clientRepository->get($clinicId, $clientId);
        } catch (ClientNotFoundException) {
            $this->logger->warning('Automated message {message} names client {client} unknown to clinic {clinic}: not journaled.', [
                'message' => $command->notificationMessageId,
                'client'  => $command->clientId,
                'clinic'  => $command->clinicId,
            ]);

            return;
        }

        // A status or channel this mirror does not know yet (Notification
        // grew one) is skipped, not retried until the message fails.
        $status  = DeliveryStatus::tryFrom($command->status);
        $channel = match ($command->channel) {
            'SMS'   => ContactChannel::SMS,
            'EMAIL' => ContactChannel::EMAIL,
            default => null,
        };

        if (null === $status || null === $channel) {
            $this->logger->warning('Automated message {message} reported an unknown status {status} or channel {channel}: not journaled.', [
                'message' => $command->notificationMessageId,
                'status'  => $command->status,
                'channel' => $command->channel,
            ]);

            return;
        }

        $at = new \DateTimeImmutable($command->occurredAt)->setTimezone(new \DateTimeZone('UTC'));

        $entry = ClientCommunication::recordAutomated(
            id: ClientCommunicationId::fromString($this->uuidGenerator->generate()),
            clinicId: $clinicId,
            clientId: $clientId,
            channel: $channel,
            subject: $command->subject,
            body: '' === trim($command->recipientLabel) ? null : 'Destinataire : ' . trim($command->recipientLabel),
            notificationMessageId: $command->notificationMessageId,
            status: $status,
            sequence: $command->sequence,
            at: $at,
        );

        if ($this->communicationRepository->addIfAbsent($entry)) {
            return;
        }

        $existing = $this->communicationRepository->findByNotificationMessageIdForUpdate($command->notificationMessageId);

        if (null === $existing) {
            // The insert found the unique key taken, yet nothing carries it: the
            // message is retried rather than its status lost.
            throw new \LogicException(\sprintf('The journal entry of automated message %s could not be read back.', $command->notificationMessageId));
        }

        if ($existing->recordDeliveryStatus($status, $command->sequence, $at)) {
            $this->communicationRepository->save($existing);
        }
    }
}
