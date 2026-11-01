<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Exception;

/**
 * The slot cannot be taken: the practitioner has an overlapping booking, the
 * block is full, or the practitioner-day lock kept deadlocking.
 */
final class SlotTakenException extends \DomainException
{
    public const string OVERLAP   = 'overlap';
    public const string CAPACITY  = 'capacity';
    public const string CONTENDED = 'contended';

    private function __construct(string $message, public readonly string $reason, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public static function overlap(string $practitionerUserId, \DateTimeImmutable $startsAtUtc): self
    {
        return new self(\sprintf(
            'Practitioner "%s" has an overlapping appointment at %s.',
            $practitionerUserId,
            $startsAtUtc->format('Y-m-d H:i'),
        ), self::OVERLAP);
    }

    public static function capacityReached(): self
    {
        return new self('Planning block capacity reached.', self::CAPACITY);
    }

    public static function contended(\Throwable $previous): self
    {
        return new self('The practitioner\'s agenda is being changed concurrently.', self::CONTENDED, $previous);
    }

    public function isCapacity(): bool
    {
        return self::CAPACITY === $this->reason;
    }
}
