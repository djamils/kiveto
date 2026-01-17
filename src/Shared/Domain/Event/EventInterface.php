<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

/**
 * Base interface for all events (domain and integration).
 */
interface EventInterface
{
    /**
     * Aggregate identifier related to this event.
     */
    public function aggregateId(): string;

    /**
     * Stable event name used for routing/serialization.
     *
     * Format: "<bounded-context>.<aggregate>.<action>.v<version>"
     * Example: "auth.user.registered.v1"
     *
     * Version is incremented ONLY for breaking payload changes.
     */
    public function name(): string;

    /**
     * Normalized event data (serialization is an infrastructure concern).
     *
     * @return array<string, mixed>
     */
    public function payload(): array;
}
