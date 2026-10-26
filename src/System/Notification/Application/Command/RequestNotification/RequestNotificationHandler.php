<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\RequestNotification;

use App\Shared\Application\Bus\ExternalApiCommandBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\Notification\Application\Command\DispatchOutboundMessage\DispatchOutboundMessage;
use App\System\Notification\Application\Exception\InvalidNotificationRequestException;
use App\System\Notification\Application\Port\MessageRendererInterface;
use App\System\Notification\Domain\Clinic\ClinicMessagingProfile;
use App\System\Notification\Domain\Clinic\ClinicMessagingProfileRepositoryInterface;
use App\System\Notification\Domain\Message\Attachment;
use App\System\Notification\Domain\Message\AttachmentOwner;
use App\System\Notification\Domain\Message\Channel;
use App\System\Notification\Domain\Message\Criticality;
use App\System\Notification\Domain\Message\IdempotencyKey;
use App\System\Notification\Domain\Message\MessagePurpose;
use App\System\Notification\Domain\Message\OutboundMessage;
use App\System\Notification\Domain\Message\OutboundMessageId;
use App\System\Notification\Domain\Message\OutboundMessageRepositoryInterface;
use App\System\Notification\Domain\Message\Recipient;
use App\System\Notification\Domain\Message\RenderedContent;
use App\System\Notification\Domain\Message\SenderIdentity;
use App\System\Notification\Domain\Message\SubjectReference;
use App\System\Notification\Domain\Quota\SmsQuotaPolicy;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Records a requested message and queues its dispatch (in the transaction of
 * the request: implicit outbox).
 *
 * - A key already used returns the existing message: nothing is sent twice.
 * - Account and test messages are sent by Kiveto; clinic messages need the
 *   clinic messaging profile — without it the message is recorded FAILED and
 *   nothing is handed to a transport (never a wrong identity).
 * - The content is rendered and frozen now (segments computed for an SMS).
 * - A non-critical SMS is checked against the clinic's overage cap under the
 *   profile row lock (held until COMMIT), counting what is sent and queued:
 *   when it would go over, the SMS is recorded BLOCKED_CAP and the requested
 *   e-mail fallback, if any, is queued instead.
 */
#[AsMessageHandler]
final readonly class RequestNotificationHandler
{
    public const string MISSING_PROFILE = "Profil d'envoi de la clinique absent";

    public function __construct(
        private OutboundMessageRepositoryInterface $messages,
        private ClinicMessagingProfileRepositoryInterface $profiles,
        private MessageRendererInterface $renderer,
        private SmsQuotaPolicy $quota,
        private ExternalApiCommandBusInterface $externalApiCommandBus,
        private DomainEventPublisher $domainEventPublisher,
        private UuidGeneratorInterface $uuids,
        private ClockInterface $clock,
        private LoggerInterface $logger,
        #[Autowire(env: 'NOTIFICATION_ACCOUNT_SENDER')]
        private string $accountSender,
        #[Autowire(env: 'NOTIFICATION_EMAIL_DOMAIN')]
        private string $emailDomain,
    ) {
    }

    public function __invoke(RequestNotification $command): string
    {
        $key      = IdempotencyKey::fromString($command->idempotencyKey);
        $existing = $this->messages->findByIdempotencyKey($key);

        if (null !== $existing) {
            return $existing->id()->toString();
        }

        $purpose   = MessagePurpose::tryFrom($command->purpose) ?? throw new InvalidNotificationRequestException(\sprintf('Unknown purpose "%s".', $command->purpose));
        $channel   = Channel::tryFrom($command->channel) ?? throw new InvalidNotificationRequestException(\sprintf('Unknown channel "%s".', $command->channel));
        $recipient = Recipient::for($channel, $command->recipientAddress, $command->recipientName);
        $subject   = SubjectReference::of($command->subjectType, $command->subjectId);
        $now       = $this->clock->now();

        if (Channel::SMS === $channel && ([] !== $command->attachments || null !== $command->fallback && Criticality::NON_CRITICAL !== $purpose->criticality())) {
            throw new InvalidNotificationRequestException('An SMS carries no attachment, and only a non-critical SMS has an e-mail fallback.');
        }

        if ($purpose->isSentByKiveto()) {
            return $this->queue($command, $purpose, $channel, $recipient, SenderIdentity::kiveto($this->accountSender), null, $subject, $key, $command->parameters, $now);
        }

        $clinicId = $command->clinicId ?? throw new InvalidNotificationRequestException(\sprintf('%s is sent on behalf of a clinic: clinicId is required.', $purpose->value));
        $capped   = Channel::SMS === $channel && Criticality::NON_CRITICAL === $purpose->criticality();
        $profile  = $capped ? $this->profiles->findForUpdate($clinicId) : $this->profiles->find($clinicId);

        if (null === $profile) {
            return $this->failForMissingProfile($command, $purpose, $channel, $recipient, $subject, $key, $clinicId, $now);
        }

        $sender = SenderIdentity::clinic($profile->name, $profile->emailLocalPart, $this->emailDomain, $profile->replyTo, $profile->smsSender);

        if ($capped) {
            $content = $this->renderer->render($purpose, $channel, $command->parameters, $profile);

            if ($this->quota->usage($clinicId, $now)->wouldReachCap($content->segments, $profile->overageCapSegments)) {
                return $this->blockForCap($command, $purpose, $recipient, $sender, $profile, $subject, $key, $content, $now);
            }
        }

        return $this->queue($command, $purpose, $channel, $recipient, $sender, $profile, $subject, $key, $command->parameters, $now);
    }

    /**
     * @param array<string, scalar|list<scalar>> $parameters
     */
    private function queue(
        RequestNotification $command,
        MessagePurpose $purpose,
        Channel $channel,
        Recipient $recipient,
        SenderIdentity $sender,
        ?ClinicMessagingProfile $profile,
        SubjectReference $subject,
        IdempotencyKey $key,
        array $parameters,
        \DateTimeImmutable $now,
    ): string {
        $message = OutboundMessage::queue(
            OutboundMessageId::fromString($this->uuids->generate()),
            $command->clinicId,
            $command->clientId,
            $purpose,
            $channel,
            $recipient,
            $sender,
            $subject,
            $key,
            $this->renderer->render($purpose, $channel, $parameters, $profile),
            Channel::EMAIL === $channel ? self::attachments($command->attachments) : [],
            $now,
        );

        $this->messages->save($message);
        $this->domainEventPublisher->publish($message);
        $this->externalApiCommandBus->send(new DispatchOutboundMessage($message->id()->toString()), ...DispatchOutboundMessage::stamps());

        return $message->id()->toString();
    }

    private function blockForCap(
        RequestNotification $command,
        MessagePurpose $purpose,
        Recipient $recipient,
        SenderIdentity $sender,
        ClinicMessagingProfile $profile,
        SubjectReference $subject,
        IdempotencyKey $key,
        RenderedContent $content,
        \DateTimeImmutable $now,
    ): string {
        $blocked = OutboundMessage::blockedByCap(
            OutboundMessageId::fromString($this->uuids->generate()),
            $profile->clinicId,
            $command->clientId,
            $purpose,
            $recipient,
            $sender,
            $subject,
            $key,
            $content,
            $now,
        );

        $this->messages->save($blocked);
        $this->domainEventPublisher->publish($blocked);

        $fallback = $command->fallback;
        if (null !== $fallback && null === $this->messages->findByIdempotencyKey($key->fallback())) {
            $this->queue(
                $command,
                $purpose,
                Channel::EMAIL,
                Recipient::for(Channel::EMAIL, $fallback->address, $fallback->name),
                $sender,
                $profile,
                $subject,
                $key->fallback(),
                $fallback->parameters,
                $now,
            );
        }

        return $blocked->id()->toString();
    }

    private function failForMissingProfile(
        RequestNotification $command,
        MessagePurpose $purpose,
        Channel $channel,
        Recipient $recipient,
        SubjectReference $subject,
        IdempotencyKey $key,
        string $clinicId,
        \DateTimeImmutable $now,
    ): string {
        $this->logger->error('Notification refused: the clinic has no messaging profile.', [
            'clinicId'       => $clinicId,
            'purpose'        => $purpose->value,
            'idempotencyKey' => $key->toString(),
        ]);

        $message = OutboundMessage::failedBeforeHandOver(
            OutboundMessageId::fromString($this->uuids->generate()),
            $clinicId,
            $command->clientId,
            $purpose,
            $channel,
            $recipient,
            $subject,
            $key,
            self::MISSING_PROFILE,
            $now,
        );

        $this->messages->save($message);
        $this->domainEventPublisher->publish($message);

        return $message->id()->toString();
    }

    /**
     * @param list<AttachmentReference> $references
     *
     * @return list<Attachment>
     */
    private static function attachments(array $references): array
    {
        return array_map(
            static fn (AttachmentReference $reference): Attachment => Attachment::of(
                $reference->storageKey,
                $reference->filename,
                $reference->mime,
                AttachmentOwner::tryFrom($reference->owner) ?? throw new InvalidNotificationRequestException(\sprintf('Unknown attachment owner "%s".', $reference->owner)),
            ),
            $references,
        );
    }
}
