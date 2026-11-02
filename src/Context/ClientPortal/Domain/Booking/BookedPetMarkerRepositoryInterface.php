<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Booking;

/**
 * The animal a « new pet » booking registered, under a marker derived from
 * the account and the request's Idempotency-Key (S4B-01): a retry of that
 * very request finds its own animal, never one of the client's the clinic
 * created. A marker is a hash, kept as long as the key answers (24 h).
 */
interface BookedPetMarkerRepositoryInterface
{
    /** The animal remembered under that marker at or after `since`, or null. */
    public function animalFor(string $marker, \DateTimeImmutable $since): ?string;

    /** Remembers (or replaces) the animal of that marker; markers older than `expiredBefore` go. */
    public function remember(string $marker, string $animalId, \DateTimeImmutable $now, \DateTimeImmutable $expiredBefore): void;
}
