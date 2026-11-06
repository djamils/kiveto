<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Service;

/**
 * A calendar day typed in a form (Y-m-d), or nothing.
 */
final class ClinicAccountDates
{
    public static function day(?string $value): ?\DateTimeImmutable
    {
        if (null === $value || '' === trim($value)) {
            return null;
        }

        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($value), new \DateTimeZone('UTC'));

        if (false === $day || $day->format('Y-m-d') !== trim($value)) {
            throw new \InvalidArgumentException(\sprintf('« %s » n’est pas une date (AAAA-MM-JJ).', $value));
        }

        return $day;
    }
}
