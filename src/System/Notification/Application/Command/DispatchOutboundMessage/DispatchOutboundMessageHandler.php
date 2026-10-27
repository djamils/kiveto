<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\DispatchOutboundMessage;

use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;
use App\Shared\Domain\Storage\FileStorageInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\Notification\Application\Command\RecordDispatchOutcome\RecordDispatchOutcome;
use App\System\Notification\Application\Exception\DeliveryRefusedException;
use App\System\Notification\Application\Port\EmailSenderInterface;
use App\System\Notification\Application\Port\OutgoingAttachment;
use App\System\Notification\Application\Port\OutgoingEmail;
use App\System\Notification\Application\Port\OutgoingSms;
use App\System\Notification\Application\Port\SmsSenderInterface;
use App\System\Notification\Application\Service\AttachmentFilename;
use App\System\Notification\Domain\Exception\OutboundMessageNotFoundException;
use App\System\Notification\Domain\Message\Attachment;
use App\System\Notification\Domain\Message\Channel;
use App\System\Notification\Domain\Message\DeliveryStatus;
use App\System\Notification\Domain\Message\OutboundMessage;
use App\System\Notification\Domain\Message\OutboundMessageId;
use App\System\Notification\Domain\Message\OutboundMessageRepositoryInterface;
use App\System\Notification\Domain\Message\Recipient;
use App\System\Notification\Domain\Message\RenderedContent;
use App\System\Notification\Domain\Message\SenderIdentity;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/**
 * Hands one queued message to the e-mail or SMS transport, outside any
 * database transaction, and records the outcome through a short command.
 *
 * - Only a QUEUED message is sent: a replayed dispatch does nothing.
 * - Attachments and the inline clinic logo are read from the file storage
 *   (binaries never travel in messages); an attachment that cannot be read
 *   fails the message.
 * - A refusal for good (4xx other than 408 / 429, delivery disabled) records
 *   FAILED with the reason; anything temporary (408, 429, 5xx, no answer) is
 *   rethrown so that Messenger retries with a growing delay, honouring the
 *   provider's Retry-After. Retries exhausted: DispatchFailureListener.
 * - Once the provider has answered, the send is never repeated: when the
 *   outcome cannot be recorded right away (database unavailable, lock
 *   conflict), it is handed to the `async` worker as its own retried
 *   RecordDispatchOutcome, and the dispatch itself succeeds. Were it rethrown,
 *   Messenger would retry the dispatch and the message — still QUEUED — would
 *   go out again.
 * - The idempotency key travels as provider tag: delivery is at-least-once
 *   (a worker dying after the provider accepted the message sends it again),
 *   and a duplicate can be traced.
 */
#[AsMessageHandler(bus: 'messenger.bus.external_api')]
final readonly class DispatchOutboundMessageHandler
{
    public const string ATTACHMENT_MISSING = 'Pièce jointe introuvable : %s';

    /** Where an outcome that could not be recorded right away waits for its retries. */
    public const string OUTCOME_TRANSPORT = 'async';

    /** Logo formats every mail client shows inline. */
    public const array LOGO_TYPES = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif'];

    public function __construct(
        private OutboundMessageRepositoryInterface $messages,
        private EmailSenderInterface $emailSender,
        private SmsSenderInterface $smsSender,
        private FileStorageInterface $storage,
        private CommandBusInterface $commandBus,
        #[Autowire(service: 'messenger.bus.command')]
        private MessageBusInterface $deferredCommandBus,
        private ClockInterface $clock,
        #[Autowire(service: 'monolog.logger.external_api')]
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(DispatchOutboundMessage $command): void
    {
        try {
            $message = $this->messages->get(OutboundMessageId::fromString($command->messageId));
        } catch (OutboundMessageNotFoundException) {
            $this->logger->warning('Notification not dispatched: no such message.', ['messageId' => $command->messageId]);

            return;
        }

        if (DeliveryStatus::QUEUED !== $message->status()) {
            return;
        }

        try {
            $externalId = Channel::EMAIL === $message->channel()
                ? $this->emailSender->send($this->email($message))
                : $this->smsSender->send($this->sms($message));
        } catch (DeliveryRefusedException $e) {
            $this->logger->warning('Notification refused by the provider.', ['messageId' => $command->messageId, 'reason' => $e->reason]);
            $this->record($command->messageId, null, $e->reason);

            return;
        } catch (ExternalServiceUnavailable $e) {
            $this->logger->warning('Notification not handed over, retried later.', ['messageId' => $command->messageId, 'error' => $e->getMessage(), 'retryAfter' => $e->retryAfter()]);

            throw $e;
        }

        $this->logger->info('Notification handed to the provider.', ['messageId' => $command->messageId, 'externalId' => $externalId, 'channel' => $message->channel()->value]);
        $this->record($command->messageId, $externalId, null);
    }

    private function email(OutboundMessage $message): OutgoingEmail
    {
        [$content, $recipient, $sender] = self::parts($message);
        $html                           = $content->html ?? '';
        $logo                           = $this->inlineLogo($message, $content->inlineLogoKey);

        if (null !== $logo) {
            // The cid carries the file extension: providers name the part after it.
            $html = str_replace('cid:' . RenderedContent::INLINE_LOGO_CID . '"', 'cid:' . $logo->filename . '"', $html);
        }

        return new OutgoingEmail(
            fromName: $sender->name,
            fromAddress: $sender->emailAddress,
            replyTo: $sender->replyTo,
            toAddress: $recipient->address,
            toName: $recipient->name,
            subject: $content->subject ?? $message->purpose()->label(),
            html: $html,
            text: $content->text ?? '',
            tag: $message->idempotencyKey()->toString(),
            attachments: array_map(
                fn (Attachment $attachment): OutgoingAttachment => new OutgoingAttachment(
                    AttachmentFilename::safe($attachment->filename),
                    $this->read($attachment->storageKey) ?? throw new DeliveryRefusedException(\sprintf(self::ATTACHMENT_MISSING, $attachment->filename)),
                    $attachment->mime,
                ),
                $message->attachments(),
            ),
            inlineLogo: $logo,
        );
    }

    private function sms(OutboundMessage $message): OutgoingSms
    {
        [$content, $recipient, $sender] = self::parts($message);

        return new OutgoingSms($sender->smsSender, $recipient->address, $content->smsText ?? '', $message->idempotencyKey()->toString());
    }

    /**
     * The clinic logo, when the content references it and its file can be
     * read; else the e-mail goes without it.
     */
    private function inlineLogo(OutboundMessage $message, ?string $key): ?OutgoingAttachment
    {
        if (null === $key) {
            return null;
        }

        $extension = mb_strtolower(pathinfo($key, \PATHINFO_EXTENSION));
        $content   = isset(self::LOGO_TYPES[$extension]) ? $this->read($key) : null;

        if (null === $content) {
            $this->logger->warning('Notification sent without its inline logo.', ['messageId' => $message->id()->toString(), 'logo' => $key]);

            return null;
        }

        return new OutgoingAttachment(RenderedContent::INLINE_LOGO_CID . '.' . $extension, $content, self::LOGO_TYPES[$extension]);
    }

    private function read(string $key): ?string
    {
        try {
            return $this->storage->read($key);
        } catch (\RuntimeException) {
            return null;
        }
    }

    /**
     * @return array{RenderedContent, Recipient, SenderIdentity}
     */
    private static function parts(OutboundMessage $message): array
    {
        $content   = $message->content();
        $recipient = $message->recipient();
        $sender    = $message->sender();

        if (null === $content || null === $recipient || null === $sender) {
            throw new \LogicException(\sprintf('Queued message %s has no content, recipient or sender.', $message->id()->toString()));
        }

        return [$content, $recipient, $sender];
    }

    /**
     * Records the provider's answer; never throws, so that an answered
     * dispatch is never retried (see the class comment).
     */
    private function record(string $messageId, ?string $externalId, ?string $error): void
    {
        $outcome = new RecordDispatchOutcome($messageId, $externalId, $error, $this->clock->now()->format(\DateTimeInterface::ATOM));

        try {
            $this->commandBus->dispatch($outcome);

            return;
        } catch (\Throwable $e) {
            $this->logger->error('Dispatch outcome not recorded, handed to the worker.', ['messageId' => $messageId, 'externalId' => $externalId, 'error' => $e->getMessage()]);
        }

        try {
            $this->deferredCommandBus->dispatch($outcome, [new TransportNamesStamp([self::OUTCOME_TRANSPORT])]);
        } catch (\Throwable $e) {
            // Nothing left to write to: the message stays « En cours », its
            // provider id is in this log line for a manual repair.
            $this->logger->critical('Dispatch outcome lost: the message stays queued.', ['messageId' => $messageId, 'externalId' => $externalId, 'error' => $e->getMessage()]);
        }
    }
}
