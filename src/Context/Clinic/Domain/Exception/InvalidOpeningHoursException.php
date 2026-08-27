<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\Exception;

final class InvalidOpeningHoursException extends \DomainException
{
    public static function unknownDay(int $day): self
    {
        return new self(\sprintf('Day "%d" is not a day of the week (1 to 7 expected).', $day));
    }

    public static function malformedTime(string $opensAt, string $closesAt): self
    {
        return new self(\sprintf('Opening slot "%s"–"%s" is not made of two HH:MM times.', $opensAt, $closesAt));
    }

    public static function closesBeforeItOpens(string $opensAt, string $closesAt): self
    {
        return new self(\sprintf('Opening slot closes at "%s", before it opens at "%s".', $closesAt, $opensAt));
    }

    public static function overlappingSlots(int $day): self
    {
        return new self(\sprintf('Two opening slots overlap on day "%d".', $day));
    }

    public static function malformedClosureDate(string $date): self
    {
        return new self(\sprintf('Closure date "%s" is not a calendar date.', $date));
    }
}
