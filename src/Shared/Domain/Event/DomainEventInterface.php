<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

interface DomainEventInterface
{
    /**
     * Unique event identifier (tracing/idempotency/replay).
     */
    public function eventId(): string;

    /**
     * Aggregate identifier related to this event.
     */
    public function aggregateId(): string;

    /**
     * When the event occurred.
     */
    public function occurredAt(): \DateTimeImmutable;

    /**
     * Stable event type used for routing/serialization.
     *
     * Format: "<bounded-context>.<aggregate>.<action>.v<version>"
     * Example: "auth.user.registered.v1"
     *
     * Version is incremented ONLY for breaking payload changes.
     */
    public function type(): string;

    /**
     * Normalized domain data (serialization is an infrastructure concern).
     *
     * @return array<string, mixed>
     */
    public function payload(): array;
}
