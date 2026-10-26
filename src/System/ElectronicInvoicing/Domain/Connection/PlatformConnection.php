<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Domain\Connection;

use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\Siren;
use App\System\ElectronicInvoicing\Domain\Event\PlatformConnectionActivated;
use App\System\ElectronicInvoicing\Domain\Exception\InvalidConnectionTransitionException;

/**
 * An organisation's link to its approved platform.
 *
 * One per organisation (SIREN), shared by every establishment that carries
 * it: invoices are found back by (seller SIREN, number) and the organisation
 * is synchronised once. It accepts submissions and is polled once the
 * platform has verified it.
 */
final class PlatformConnection extends AggregateRoot
{
    private function __construct(
        private readonly PlatformConnectionId $id,
        private readonly Siren $organisationId,
        private readonly PlatformCode $platform,
        private OnboardingState $state,
        private ?string $rejectionReason,
        private SyncCursor $cursor,
        private readonly \DateTimeImmutable $openedAt,
        private ?\DateTimeImmutable $verifiedAt,
    ) {
    }

    public static function open(PlatformConnectionId $id, Siren $organisationId, PlatformCode $platform, \DateTimeImmutable $now): self
    {
        // Platforms date flows to the second: start from the second of the
        // opening so a flow of that very second is not skipped.
        $from = $now->setTime((int) $now->format('H'), (int) $now->format('i'), (int) $now->format('s'));

        return new self($id, $organisationId, $platform, OnboardingState::PENDING, null, SyncCursor::at($from), $now, null);
    }

    public static function reconstitute(
        PlatformConnectionId $id,
        Siren $organisationId,
        PlatformCode $platform,
        OnboardingState $state,
        ?string $rejectionReason,
        SyncCursor $cursor,
        \DateTimeImmutable $openedAt,
        ?\DateTimeImmutable $verifiedAt,
    ): self {
        return new self($id, $organisationId, $platform, $state, $rejectionReason, $cursor, $openedAt, $verifiedAt);
    }

    public function markVerified(\DateTimeImmutable $now): void
    {
        if (OnboardingState::PENDING !== $this->state) {
            throw new InvalidConnectionTransitionException($this->id->toString(), $this->state, OnboardingState::VERIFIED);
        }

        $this->state      = OnboardingState::VERIFIED;
        $this->verifiedAt = $now;

        $this->recordDomainEvent(new PlatformConnectionActivated(
            connectionId: $this->id->toString(),
            organisationId: $this->organisationId->value(),
            activatedAt: $now->format(\DateTimeInterface::ATOM),
        ));
    }

    public function markRejected(string $reason): void
    {
        if (OnboardingState::PENDING !== $this->state) {
            throw new InvalidConnectionTransitionException($this->id->toString(), $this->state, OnboardingState::REJECTED);
        }

        $this->state           = OnboardingState::REJECTED;
        $this->rejectionReason = $reason;
    }

    /**
     * Moves the cursor forward; a cursor that is not after the current one is ignored.
     */
    public function advanceCursor(SyncCursor $cursor): void
    {
        if ($cursor->isAfter($this->cursor)) {
            $this->cursor = $cursor;
        }
    }

    public function acceptsSubmissions(): bool
    {
        return OnboardingState::VERIFIED === $this->state;
    }

    public function isPolled(): bool
    {
        return OnboardingState::VERIFIED === $this->state;
    }

    public function id(): PlatformConnectionId
    {
        return $this->id;
    }

    public function organisationId(): Siren
    {
        return $this->organisationId;
    }

    public function platform(): PlatformCode
    {
        return $this->platform;
    }

    public function state(): OnboardingState
    {
        return $this->state;
    }

    public function rejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function cursor(): SyncCursor
    {
        return $this->cursor;
    }

    public function openedAt(): \DateTimeImmutable
    {
        return $this->openedAt;
    }

    public function verifiedAt(): ?\DateTimeImmutable
    {
        return $this->verifiedAt;
    }
}
