<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

/**
 * Base class for domain events.
 *
 * Event type versioning:
 * - Keep VERSION unchanged for backward-compatible payload changes (e.g. adding a new optional field).
 * - Increment VERSION only for breaking changes (renaming/removing/changing meaning or format of fields).
 *
 * Event type format: "<bounded-context>.<aggregate>.<action>.v<version>"
 */
abstract class AbstractDomainEvent implements DomainEventInterface
{
    /**
     * Override in child event (e.g. "auth", "clinic", "billing").
     *
     * @var string
     */
    protected const BOUNDED_CONTEXT = 'shared';

    /**
     * Increment only for breaking payload changes.
     *
     * @var int
     */
    protected const VERSION = 1;

    private readonly string $eventId;

    private readonly \DateTimeImmutable $occurredAt;

    public function __construct(string $eventId, \DateTimeImmutable $occurredAt)
    {
        $this->eventId    = $eventId;
        $this->occurredAt = $occurredAt;
    }

    final public function eventId(): string
    {
        return $this->eventId;
    }

    final public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    final public function type(): string
    {
        [$aggregate, $action] = self::inferAggregateAndActionFromClass(static::class);

        /** @var string $boundedContext */
        $boundedContext = static::BOUNDED_CONTEXT;
        /** @var int $version */
        $version = static::VERSION;

        return \sprintf(
            '%s.%s.%s.v%d',
            $boundedContext,
            self::toKebabLower($aggregate),
            self::toKebabLower($action),
            $version,
        );
    }

    /**
     * @return array{0: string, 1: string} [aggregate, action]
     */
    private static function inferAggregateAndActionFromClass(string $fqcn): array
    {
        $shortNamePos = mb_strrpos($fqcn, '\\');
        $shortName    = false === $shortNamePos ? $fqcn : mb_substr($fqcn, $shortNamePos + 1);

        $tokens = self::splitCamelCase($shortName);

        if (\count($tokens) < 2) {
            return [$shortName, 'occurred'];
        }

        $action    = (string) array_pop($tokens);
        $aggregate = implode('', $tokens);

        return [$aggregate, $action];
    }

    /**
     * @return list<string>
     */
    private static function splitCamelCase(string $value): array
    {
        return preg_split('/(?<!^)(?=[A-Z])/', $value) ?: [];

        /* @var list<string> $parts */
    }

    private static function toKebabLower(string $value): string
    {
        $value = preg_replace('/(?<!^)(?=[A-Z])/', '-', $value) ?? $value;

        return mb_strtolower($value);
    }
}
