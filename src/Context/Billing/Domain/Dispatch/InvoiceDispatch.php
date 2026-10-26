<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\Dispatch;

use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;

/**
 * One « Envoyer » or « Relancer » of an invoice (D10): logged when the staff
 * asks for it, on the channels chosen then; the Notification messages it
 * produced are recorded once the asynchronous send has requested them. The
 * log feeds the invoice history (« Envoi par e-mail le … », « relancée 2
 * fois ») and the same-day reminder guard.
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
    ): self {
        return new self($id, $clinicId, $invoiceId, $kind, $channels, $notificationIds, $authorUserId, $createdAt);
    }

    /**
     * The messages the send requested. Recorded once: a replayed send finds
     * them and requests nothing again.
     *
     * @param list<string> $notificationIds
     */
    public function recordNotifications(array $notificationIds): void
    {
        if ($this->wasSent()) {
            throw new \LogicException('The messages of this dispatch are already recorded.');
        }

        $this->notificationIds = $notificationIds;
    }

    public function wasSent(): bool
    {
        return [] !== $this->notificationIds;
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
