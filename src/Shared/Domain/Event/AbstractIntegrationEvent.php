<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

/**
 * Base class for integration events.
 *
 * Integration events are used for cross-bounded-context communication.
 * They are routed to async transport and should not contain domain objects.
 *
 * Event name versioning:
 * - Keep VERSION unchanged for backward-compatible payload changes (e.g. adding a new optional field).
 * - Increment VERSION only for breaking changes (renaming/removing/changing meaning or format of fields).
 *
 * Event name format: "<bounded-context>.<aggregate>.<action>.v<version>"
 */
abstract readonly class AbstractIntegrationEvent extends AbstractEvent implements IntegrationEventInterface
{
}
