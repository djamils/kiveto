<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\Dispatch;

use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;

/**
 * One « Envoyer » or « Relancer » of an invoice (D10): logged when the staff
 * asks for it, on the channels chosen then; the Notification messages it
 * produced are recorded once the asynchronous send has requested them — or,
 * when the send found nothing to send by then, why it was skipped. The log
 * feeds the invoice history (« Envoi par e-mail le … », « relancée 2 fois »,
 * « non envoyée (motif) ») and the same-day reminder guard, which a skipped
 * reminder does not hold back.
 */
final class InvoiceDispatch
{
    public const string EMAIL = 'EMAIL';
    public const string SMS   = 'SMS';

    /**
     * @param list<string> $channels        EMAIL and / or SMS
     * @param list<string> $notificationIds Notification message ids, empty until the send ran
     */
    private function __construct(
        private readonly InvoiceDispatchId $id,
        private readonly ClinicId $clinicId,
        private readonly InvoiceId $invoiceId,
        private readonly DispatchKind $kind,
        private readonly array $channels,
        private array $notificationIds,
        private readonly ?string $authorUserId,
        private readonly \DateTimeImmutable $createdAt,
        private ?string $skippedReason = null,
    ) {
    }

    /**
     * @param list<string> $channels
     */
    public static function log(
        InvoiceDispatchId $id,
        ClinicId $clinicId,
        InvoiceId $invoiceId,
        DispatchKind $kind,
        array $channels,
        ?string $authorUserId,
        \DateTimeImmutable $at,
    ): self {
        if ([] === $channels || [] !== array_diff($channels, [self::EMAIL, self::SMS])) {
            throw new \InvalidArgumentException('An invoice is sent by e-mail and / or SMS.');
        }

        return new self($id, $clinicId, $invoiceId, $kind, array_values(array_unique($channels)), [], $authorUserId, $at);
    }

    /**
     * @param list<string> $channels
     * @param list<string> $notificationIds
     */
    public static function reconstitute(
        InvoiceDispatchId $id,
        ClinicId $clinicId,
        InvoiceId $invoiceId,
        DispatchKind $kind,
        array $channels,
        array $notificationIds,
        ?string $authorUserId,
        \DateTimeImmutable $createdAt,
        ?string $skippedReason = null,
    ): self {
        return new self($id, $clinicId, $invoiceId, $kind, $channels, $notificationIds, $authorUserId, $createdAt, $skippedReason);
    }

    /**
     * The messages the send requested. Recorded once: a replayed send finds
     * them and requests nothing again.
     *
     * @param list<string> $notificationIds
     */
    public function recordNotifications(array $notificationIds): void
    {
        if ($this->isSettled()) {
            throw new \LogicException('The outcome of this dispatch is already recorded.');
        }

        if ([] === $notificationIds) {
            throw new \InvalidArgumentException('A send records at least one message; skip it otherwise.');
        }

        $this->notificationIds = $notificationIds;
    }

    /**
     * The send found nothing to send any more (invoice settled or cancelled,
     * address or consent withdrawn): the reason the history shows.
     */
    public function skip(string $reason): void
    {
        if ($this->isSettled()) {
            throw new \LogicException('The outcome of this dispatch is already recorded.');
        }

        $this->skippedReason = $reason;
    }

    public function wasSent(): bool
    {
        return [] !== $this->notificationIds;
    }

    public function wasSkipped(): bool
    {
        return null !== $this->skippedReason;
    }

    /** Sent or skipped: the asynchronous send has nothing left to do. */
    public function isSettled(): bool
    {
        return $this->wasSent() || $this->wasSkipped();
    }

    public function skippedReason(): ?string
    {
        return $this->skippedReason;
    }

    public function id(): InvoiceDispatchId
    {
        return $this->id;
    }

    public function clinicId(): ClinicId
    {
        return $this->clinicId;
    }

    public function invoiceId(): InvoiceId
    {
        return $this->invoiceId;
    }

    public function kind(): DispatchKind
    {
        return $this->kind;
    }

    /**
     * @return list<string>
     */
    public function channels(): array
    {
        return $this->channels;
    }

    public function sendsOn(string $channel): bool
    {
        return \in_array($channel, $this->channels, true);
    }

    /**
     * @return list<string>
     */
    public function notificationIds(): array
    {
        return $this->notificationIds;
    }

    public function authorUserId(): ?string
    {
        return $this->authorUserId;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
