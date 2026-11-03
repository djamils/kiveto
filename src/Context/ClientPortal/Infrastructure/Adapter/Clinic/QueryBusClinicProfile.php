<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Adapter\Clinic;

use App\Context\ClientPortal\Application\Port\ClinicAddress;
use App\Context\ClientPortal\Application\Port\ClinicContact;
use App\Context\ClientPortal\Application\Port\ClinicLabel;
use App\Context\ClientPortal\Application\Port\ClinicProfileInterface;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\ClinicSettingsView;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\GetClinicSettings;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Document\DocumentClinic;

/**
 * Clinic's GetClinic (its name, time zone and letterhead) and
 * GetClinicSettings (the letterhead's phone and address, read as the
 * directory reads them).
 */
final readonly class QueryBusClinicProfile implements ClinicProfileInterface
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function clinicOf(string $clinicId): ClinicLabel
    {
        $clinic = $this->clinic($clinicId);

        if (null === $clinic) {
            return new ClinicLabel('', null);
        }

        $city = $clinic->letterhead?->city;

        return new ClinicLabel($clinic->name, null !== $city && '' !== mb_trim($city) ? $city : null, $clinic->timeZone);
    }

    public function documentHeaderOf(string $clinicId): ?DocumentClinic
    {
        return $this->clinic($clinicId)?->toDocumentClinic();
    }

    public function contactDetailsOf(string $clinicId): ClinicContact
    {
        $settings = $this->queryBus->ask(new GetClinicSettings($clinicId));

        if (!$settings instanceof ClinicSettingsView) {
            return new ClinicContact(null, null);
        }

        $letterhead = $settings->letterhead;
        $phone      = $letterhead['phone'] ?? null;
        $line       = implode(', ', array_filter(
            [self::clean($letterhead['streetLine1'] ?? null), self::clean($letterhead['streetLine2'] ?? null)],
            static fn (?string $part): bool => null !== $part,
        ));
        $city = self::clean($letterhead['city'] ?? null);

        return new ClinicContact(
            null !== $phone && '' !== mb_trim($phone) ? $phone : null,
            '' === $line || null === $city ? null : new ClinicAddress($line, self::clean($letterhead['postalCode'] ?? null), $city),
        );
    }

    private function clinic(string $clinicId): ?ClinicDto
    {
        $clinic = $this->queryBus->ask(new GetClinic($clinicId));

        return $clinic instanceof ClinicDto ? $clinic : null;
    }

    /** Spaces collapsed and trimmed; null when nothing is left. */
    private static function clean(?string $value): ?string
    {
        $value = mb_trim((string) preg_replace('/\s+/u', ' ', $value ?? ''));

        return '' === $value ? null : $value;
    }
}
