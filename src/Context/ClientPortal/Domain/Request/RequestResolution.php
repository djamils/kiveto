<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Request;

use App\Context\ClientPortal\Domain\Exception\InvalidPortalRequestException;

/**
 * The clinic's answer to a request: « Traiter » (`done`) or « Refuser »
 * (`rejected`), who answered (name snapshotted) and an optional message to
 * the owner (at most 280 characters, trimmed, blank = none).
 */
final readonly class RequestResolution
{
    public const int MAX_MESSAGE = 280;

    private RequestStatus $outcome;
    private \DateTimeImmutable $resolvedAt;
    private string $resolvedByUserId;
    private string $resolvedByName;
    private ?string $message;

    private function __construct(RequestStatus $outcome, \DateTimeImmutable $resolvedAt, string $resolvedByUserId, string $resolvedByName, ?string $message)
    {
        $this->outcome          = $outcome;
        $this->resolvedAt       = $resolvedAt;
        $this->resolvedByUserId = $resolvedByUserId;
        $this->resolvedByName   = $resolvedByName;
        $this->message          = $message;
    }

    /**
     * @throws InvalidPortalRequestException when the message is too long
     * @throws \InvalidArgumentException     when the outcome is neither done nor rejected
     */
    public static function of(RequestStatus $outcome, string $resolvedByUserId, string $resolvedByName, ?string $message, \DateTimeImmutable $resolvedAt): self
    {
        if (RequestStatus::DONE !== $outcome && RequestStatus::REJECTED !== $outcome) {
            throw new \InvalidArgumentException(\sprintf('A request is resolved as done or rejected, not "%s".', $outcome->value));
        }

        $message = null === $message || '' === trim($message) ? null : trim($message);

        if (null !== $message && mb_strlen($message) > self::MAX_MESSAGE) {
            throw new InvalidPortalRequestException('message', \sprintf('Le message ne doit pas dépasser %d caractères.', self::MAX_MESSAGE));
        }

        return new self($outcome, $resolvedAt, $resolvedByUserId, $resolvedByName, $message);
    }

    public static function reconstitute(RequestStatus $outcome, \DateTimeImmutable $resolvedAt, string $resolvedByUserId, string $resolvedByName, ?string $message): self
    {
        return new self($outcome, $resolvedAt, $resolvedByUserId, $resolvedByName, $message);
    }

    public function outcome(): RequestStatus
    {
        return $this->outcome;
    }

    public function resolvedAt(): \DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function resolvedByUserId(): string
    {
        return $this->resolvedByUserId;
    }

    public function resolvedByName(): string
    {
        return $this->resolvedByName;
    }

    public function message(): ?string
    {
        return $this->message;
    }
}
