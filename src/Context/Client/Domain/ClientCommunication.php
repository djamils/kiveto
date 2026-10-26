<?php

declare(strict_types=1);

namespace App\Context\Client\Domain;

use App\Context\Client\Domain\Event\ClientCommunicationLogged;
use App\Context\Client\Domain\Exception\ManualCommunicationCannotChangeException;
use App\Context\Client\Domain\ValueObject\ClientCommunicationId;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Domain\ValueObject\CommunicationDirection;
use App\Context\Client\Domain\ValueObject\CommunicationOrigin;
use App\Context\Client\Domain\ValueObject\ContactChannel;
use App\Context\Client\Domain\ValueObject\DeliveryStatus;
use App\Shared\Domain\Aggregate\AggregateRoot;

/**
 * One exchange with a client (call, SMS, e-mail, letter). It is a journal
 * entry: it does not send anything by itself.
 *
 * Entries logged by the staff (MANUAL) never change. Entries recording a
 * message Kiveto sent on its own (AUTOMATED) only change their delivery
 * status, which follows the Notification message they point to.
 */
final class ClientCommunication extends AggregateRoot
{
    public const int MAX_SUBJECT_LENGTH = 160;
    public const int MAX_BODY_LENGTH    = 4000;

    /** The author shown on automated entries. */
    public const string AUTOMATED_AUTHOR = 'Envoi automatique';

    private ClientCommunicationId $id;
    private ClinicId $clinicId;
    private ClientId $clientId;
    private ContactChannel $channel;
    private CommunicationDirection $direction;
    private string $subject;
    private ?string $body;
    private string $authorName;
    private \DateTimeImmutable $occurredAt;
    private CommunicationOrigin $origin;
    private ?DeliveryStatus $deliveryStatus;
    private ?string $notificationMessageId;
    private ?int $deliveryStatusSequence;
    private ?\DateTimeImmutable $statusChangedAt;

    private function __construct()
    {
    }

    public static function log(
        ClientCommunicationId $id,
        ClinicId $clinicId,
        ClientId $clientId,
        ContactChannel $channel,
        CommunicationDirection $direction,
        string $subject,
        ?string $body,
        string $authorName,
        \DateTimeImmutable $occurredAt,
    ): self {
        $communication = self::reconstitute(
            $id,
            $clinicId,
            $clientId,
            $channel,
            $direction,
            $subject,
            $body,
            $authorName,
            $occurredAt,
        );

        $communication->recordDomainEvent(new ClientCommunicationLogged(
            communicationId: $id->toString(),
            clientId: $clientId->toString(),
            clinicId: $clinicId->toString(),
            channel: $channel->value,
            direction: $direction->value,
        ));

        return $communication;
    }

    public static function reconstitute(
        ClientCommunicationId $id,
        ClinicId $clinicId,
        ClientId $clientId,
        ContactChannel $channel,
        CommunicationDirection $direction,
        string $subject,
        ?string $body,
        string $authorName,
        \DateTimeImmutable $occurredAt,
        CommunicationOrigin $origin = CommunicationOrigin::MANUAL,
        ?DeliveryStatus $deliveryStatus = null,
        ?string $notificationMessageId = null,
        ?int $deliveryStatusSequence = null,
        ?\DateTimeImmutable $statusChangedAt = null,
    ): self {
        $subject    = trim($subject);
        $body       = null === $body ? '' : trim($body);
        $authorName = trim($authorName);

        if ('' === $subject) {
            throw new \InvalidArgumentException('A communication needs a subject.');
        }

        if (mb_strlen($subject) > self::MAX_SUBJECT_LENGTH) {
            throw new \InvalidArgumentException(
                \sprintf('A communication subject cannot exceed %d characters.', self::MAX_SUBJECT_LENGTH),
            );
        }

        if (mb_strlen($body) > self::MAX_BODY_LENGTH) {
            throw new \InvalidArgumentException(
                \sprintf('A communication body cannot exceed %d characters.', self::MAX_BODY_LENGTH),
            );
        }

        if ('' === $authorName) {
            throw new \InvalidArgumentException('A communication needs an author.');
        }

        $tracked = [$deliveryStatus, $notificationMessageId, $deliveryStatusSequence, $statusChangedAt];

        $complete = CommunicationOrigin::AUTOMATED === $origin
            ? !\in_array(null, $tracked, true)
            : [null, null, null, null] === $tracked;

        if (!$complete) {
            throw new \InvalidArgumentException('Only an automated communication, and every one of them, tracks a delivery status.');
        }

        $communication             = new self();
        $communication->id         = $id;
        $communication->clinicId   = $clinicId;
        $communication->clientId   = $clientId;
        $communication->channel    = $channel;
        $communication->direction  = $direction;
        $communication->subject    = $subject;
        $communication->body       = '' === $body ? null : $body;
        $communication->authorName = $authorName;
        $communication->occurredAt = $occurredAt;

        $communication->origin                 = $origin;
        $communication->deliveryStatus         = $deliveryStatus;
        $communication->notificationMessageId  = $notificationMessageId;
        $communication->deliveryStatusSequence = $deliveryStatusSequence;
        $communication->statusChangedAt        = $statusChangedAt;

        return $communication;
    }

    /**
     * A message Kiveto sent to the client, journaled from the first status
     * the Notification context reports for it (any status: events may come
     * out of order).
     */
    public static function recordAutomated(
        ClientCommunicationId $id,
        ClinicId $clinicId,
        ClientId $clientId,
        ContactChannel $channel,
        string $subject,
        ?string $body,
        string $notificationMessageId,
        DeliveryStatus $status,
        int $sequence,
        \DateTimeImmutable $at,
    ): self {
        return self::reconstitute(
            $id,
            $clinicId,
            $clientId,
            $channel,
            CommunicationDirection::OUTBOUND,
            mb_substr(trim($subject), 0, self::MAX_SUBJECT_LENGTH),
            $body,
            self::AUTOMATED_AUTHOR,
            $at,
            CommunicationOrigin::AUTOMATED,
            $status,
            $notificationMessageId,
            $sequence,
            $at,
        );
    }

    /**
     * Follows the message's status. Only a newer report (higher sequence)
     * changes it; an older one is ignored but may date the entry earlier,
     * since an entry is dated by the first status of its message.
     *
     * @return bool whether anything changed
     */
    public function recordDeliveryStatus(DeliveryStatus $status, int $sequence, \DateTimeImmutable $at): bool
    {
        if (CommunicationOrigin::MANUAL === $this->origin) {
            throw new ManualCommunicationCannotChangeException($this->id->toString());
        }

        $changed = false;

        if ($at < $this->occurredAt) {
            $this->occurredAt = $at;
            $changed          = true;
        }

        if ($sequence <= (int) $this->deliveryStatusSequence) {
            return $changed;
        }

        $this->deliveryStatus         = $status;
        $this->deliveryStatusSequence = $sequence;
        $this->statusChangedAt        = $at;

        return true;
    }

    public function id(): ClientCommunicationId
    {
        return $this->id;
    }

    public function clinicId(): ClinicId
    {
        return $this->clinicId;
    }

    public function clientId(): ClientId
    {
        return $this->clientId;
    }

    public function channel(): ContactChannel
    {
        return $this->channel;
    }

    public function direction(): CommunicationDirection
    {
        return $this->direction;
    }

    public function subject(): string
    {
        return $this->subject;
    }

    public function body(): ?string
    {
        return $this->body;
    }

    public function authorName(): string
    {
        return $this->authorName;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function origin(): CommunicationOrigin
    {
        return $this->origin;
    }

    public function isAutomated(): bool
    {
        return CommunicationOrigin::AUTOMATED === $this->origin;
    }

    public function deliveryStatus(): ?DeliveryStatus
    {
        return $this->deliveryStatus;
    }

    /** The Notification message id (not the provider's) of an automated entry. */
    public function notificationMessageId(): ?string
    {
        return $this->notificationMessageId;
    }

    public function deliveryStatusSequence(): ?int
    {
        return $this->deliveryStatusSequence;
    }

    public function statusChangedAt(): ?\DateTimeImmutable
    {
        return $this->statusChangedAt;
    }
}
