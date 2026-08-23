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
     * @param array<string, string|int|null>                             $billing
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
    ) {
    }

    public function isOpenOn(int $day): bool
    {
        return [] !== ($this->week[$day] ?? []);
    }
}
