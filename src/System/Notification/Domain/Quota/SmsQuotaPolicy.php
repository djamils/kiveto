<?php

declare(strict_types=1);

namespace App\System\Notification\Domain\Quota;

use App\System\Notification\Domain\Message\OutboundMessageRepositoryInterface;

/**
 * The monthly SMS quota of a clinic and what it consumed.
 *
 * The quota month is a Kiveto billing month, always computed in
 * Europe/Paris. Quota = segments per veterinarian × the highest number of
 * active veterinarians over the month's daily counts; a month without any
 * count uses the last known one; the count is never below 1. Usage is the
 * segments of the SMS handed to the provider during the month.
 */
final readonly class SmsQuotaPolicy
{
    public const string TIME_ZONE = 'Europe/Paris';

    private int $unitPriceMicros;

    /**
     * @param string $overageUnitPrice euros HT per segment, e.g. '0.079'
     */
    public function __construct(
        private OutboundMessageRepositoryInterface $messages,
        private VeterinarianCountRepositoryInterface $counts,
        private int $segmentsPerVeterinarian,
        string $overageUnitPrice,
    ) {
        if ($segmentsPerVeterinarian < 1) {
            throw new \InvalidArgumentException('A veterinarian is granted at least one segment.');
        }

        if (1 !== preg_match('/^(\d+)(?:\.(\d{1,6}))?$/', $overageUnitPrice, $price)) {
            throw new \InvalidArgumentException(\sprintf('Invalid overage unit price "%s".', $overageUnitPrice));
        }

        $this->unitPriceMicros = (int) $price[1] * 1_000_000 + (int) mb_str_pad($price[2] ?? '', 6, '0');
    }

    public function usage(string $clinicId, \DateTimeImmutable $now): SmsUsage
    {
        [$start, $end] = self::month($now);

        $veterinarians = $this->counts->maxBetween($clinicId, $start, $end->modify('-1 day'))
            ?? $this->counts->lastKnownBefore($clinicId, $start)
            ?? 1;
        $quota   = $this->segmentsPerVeterinarian * max(1, $veterinarians);
        $used    = $this->messages->sumBilledSegments($clinicId, $start, $end);
        $overage = max(0, $used - $quota);

        return new SmsUsage(
            month: $start->format('Y-m'),
            quota: $quota,
            used: $used,
            queued: $this->messages->sumQueuedSegments($clinicId),
            overageAmountCents: intdiv($overage * $this->unitPriceMicros + 5_000, 10_000),
        );
    }

    /**
     * The quota month holding $now: [first day 00:00, first day of the next month 00:00[, Europe/Paris.
     *
     * @return array{\DateTimeImmutable, \DateTimeImmutable}
     */
    public static function month(\DateTimeImmutable $now): array
    {
        $start = $now->setTimezone(new \DateTimeZone(self::TIME_ZONE))->modify('first day of this month')->setTime(0, 0);

        return [$start, $start->modify('first day of next month')];
    }
}
