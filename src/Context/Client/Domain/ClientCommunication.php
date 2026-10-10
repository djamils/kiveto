<?php

declare(strict_types=1);

namespace App\Context\Client\Domain;

use App\Context\Client\Domain\Event\ClientCommunicationLogged;
use App\Context\Client\Domain\ValueObject\ClientCommunicationId;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Domain\ValueObject\CommunicationDirection;
use App\Context\Client\Domain\ValueObject\ContactChannel;
use App\Shared\Domain\Aggregate\AggregateRoot;

/**
 * One exchange with a client (call, SMS, e-mail, letter) recorded by the
 * staff. It is a journal entry: it does not send anything by itself.
 */
final class ClientCommunication extends AggregateRoot
{
    public const int MAX_SUBJECT_LENGTH = 160;
    public const int MAX_BODY_LENGTH    = 4000;

    private ClientCommunicationId $id;
    private ClinicId $clinicId;
    private ClientId $clientId;
    private ContactChannel $channel;
    private CommunicationDirection $direction;
    private string $subject;
    private ?string $body;
    private string $authorName;
    private \DateTimeImmutable $occurredAt;

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

        return $communication;
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
}
