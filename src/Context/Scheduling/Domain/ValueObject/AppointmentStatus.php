<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\ValueObject;

/**
 * Lifecycle of an appointment. This enum is the single source of truth on
 * what a status means: outside Scheduling, nobody compares status strings —
 * read models expose `occupiesSlot`, `isPending` and `isWithdrawn` instead.
 */
enum AppointmentStatus: string
{
    case PLANNED                     = 'PLANNED';
    case PENDING_CLINIC_CONFIRMATION = 'PENDING_CLINIC_CONFIRMATION';
    case CANCELLED                   = 'CANCELLED';
    case DECLINED                    = 'DECLINED';
    case NO_SHOW                     = 'NO_SHOW';
    case COMPLETED                   = 'COMPLETED';

    /**
     * The statuses that hold their slot, for raw SQL (`status IN (…)`).
     * Kept equal to occupiesSlot() by a unit test.
     */
    public const array OCCUPYING_SQL_VALUES = [self::PLANNED->value, self::PENDING_CLINIC_CONFIRMATION->value];

    public function isTerminal(): bool
    {
        return \in_array($this, [self::CANCELLED, self::NO_SHOW, self::COMPLETED, self::DECLINED], true);
    }

    /**
     * Whether the appointment holds its slot: nobody else can be booked on
     * it, and it counts towards the block capacity.
     */
    public function occupiesSlot(): bool
    {
        return self::PLANNED === $this || self::PENDING_CLINIC_CONFIRMATION === $this;
    }

    /**
     * Booked online, waiting for the clinic's confirmation (« à confirmer »).
     */
    public function isPending(): bool
    {
        return self::PENDING_CLINIC_CONFIRMATION === $this;
    }

    /**
     * Cancelled, or declined by the clinic: shown as cancelled, never counted.
     */
    public function isWithdrawn(): bool
    {
        return self::CANCELLED === $this || self::DECLINED === $this;
    }
}
