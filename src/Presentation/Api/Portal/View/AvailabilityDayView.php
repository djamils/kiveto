<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

use ApiPlatform\Metadata\ApiProperty;
use App\Context\Scheduling\Application\Query\GetOnlineAvailability\OnlineAvailabilityDay;
use App\Presentation\Api\Portal\Support\ApiDate;

/** A clinic-local day of online availability and its bookable starts. */
final readonly class AvailabilityDayView
{
    /**
     * @param list<AvailabilitySlotView> $slots
     */
    public function __construct(
        #[ApiProperty(schema: ['type' => 'string', 'format' => 'date'])]
        public string $date,
        /** An opening day, not an exceptional closure, within the horizon. */
        public bool $open,
        public array $slots,
    ) {
    }

    public static function of(OnlineAvailabilityDay $day): self
    {
        return new self(
            $day->date,
            $day->open,
            array_map(static fn (\DateTimeImmutable $start): AvailabilitySlotView => new AvailabilitySlotView(ApiDate::formatLocal($start)), $day->slots),
        );
    }
}
