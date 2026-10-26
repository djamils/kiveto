<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Clinic\GetClinicSettings;

/**
 * The clinic as its own settings pages need it: raw values, ready to be put back
 * into the fields they came from.
 */
final readonly class ClinicSettingsView
{
    /**
     * @param array<string, string|null>                                 $letterhead
     * @param array<int, list<array{opensAt: string, closesAt: string}>> $week
     * @param list<array{date: string, reason: string|null}>             $closures
     * @param array<string, string|int|bool|list<string>|null>           $billing
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public string $status,
        public string $timeZone,
        public string $locale,
        public string $countryCode,
        public string $currencyCode,
        public array $letterhead,
        public array $week,
        public array $closures,
        public array $billing,
        public ?string $brandColor = null,
        /** The stored file of the logo, null when there is none. */
        public ?string $logoKey = null,
        /** The name the clinic's SMS come from (chosen, or derived from its name). */
        public string $smsSender = '',
        /** Segments accepted beyond the monthly SMS quota. */
        public int $overageCapSegments = 0,
    ) {
    }

    public function isOpenOn(int $day): bool
    {
        return [] !== ($this->week[$day] ?? []);
    }
}
