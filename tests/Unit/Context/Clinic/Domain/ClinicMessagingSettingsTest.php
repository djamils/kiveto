<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Domain;

use App\Context\Clinic\Domain\Clinic;
use App\Context\Clinic\Domain\Event\ClinicMessagingSettingsChanged;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Context\Clinic\Domain\ValueObject\ClinicSlug;
use App\Context\Clinic\Domain\ValueObject\MessagingSettings;
use App\Shared\Domain\Localization\Locale;
use App\Shared\Domain\Localization\TimeZone;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use PHPUnit\Framework\TestCase;

final class ClinicMessagingSettingsTest extends TestCase
{
    public function testUntilChosenTheSettingsFollowTheName(): void
    {
        $clinic = self::clinic();

        self::assertNull($clinic->chosenMessagingSettings());
        self::assertSame('Clinique de', $clinic->messagingSettings()->smsSender);
        self::assertSame(500, $clinic->messagingSettings()->overageCapSegments);

        $clinic->rename('Véto Lyon', new \DateTimeImmutable('2026-02-01'));

        self::assertSame('Veto Lyon', $clinic->messagingSettings()->smsSender);
    }

    public function testChoosingRecordsAnEventOnceAndOutlivesARename(): void
    {
        $clinic = self::clinic();
        self::assertNotEmpty($clinic->pullDomainEvents());
        $at = new \DateTimeImmutable('2026-03-01T08:00:00Z');

        $clinic->changeMessagingSettings(MessagingSettings::create('Lilas Vet', 300), $at);
        $clinic->changeMessagingSettings(MessagingSettings::create('Lilas Vet', 300), new \DateTimeImmutable('2026-04-01'));

        $events = $clinic->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ClinicMessagingSettingsChanged::class, $events[0]);
        self::assertSame(['clinicId' => '018f1b1e-1234-7890-abcd-0123456789ab', 'smsSender' => 'Lilas Vet', 'overageCapSegments' => 300], $events[0]->payload());
        self::assertSame('018f1b1e-1234-7890-abcd-0123456789ab', $events[0]->aggregateId());
        self::assertSame($at, $clinic->updatedAt());

        $clinic->rename('Autre nom', new \DateTimeImmutable('2026-05-01'));
        self::assertSame('Lilas Vet', $clinic->messagingSettings()->smsSender);
        self::assertSame('Lilas Vet', $clinic->chosenMessagingSettings()?->smsSender);
    }

    public function testReconstitutionKeepsTheChosenSettings(): void
    {
        $clinic = Clinic::reconstitute(
            id: ClinicId::fromString('018f1b1e-1234-7890-abcd-0123456789ab'),
            name: 'Clinique des Lilas',
            slug: ClinicSlug::fromString('lilas'),
            timeZone: TimeZone::fromString('Europe/Paris'),
            locale: Locale::fromString('fr-FR'),
            countryCode: CountryCode::fromString('FR'),
            currencyCode: CurrencyCode::fromString('EUR'),
            status: \App\Context\Clinic\Domain\ValueObject\ClinicStatus::ACTIVE,
            createdAt: new \DateTimeImmutable('2026-01-01'),
            updatedAt: new \DateTimeImmutable('2026-01-01'),
            messagingSettings: MessagingSettings::create('Lilas', 42),
        );

        self::assertSame(42, $clinic->messagingSettings()->overageCapSegments);
    }

    private static function clinic(): Clinic
    {
        return Clinic::create(
            ClinicId::fromString('018f1b1e-1234-7890-abcd-0123456789ab'),
            'Clinique des Lilas',
            ClinicSlug::fromString('clinique-des-lilas'),
            TimeZone::fromString('Europe/Paris'),
            Locale::fromString('fr-FR'),
            CountryCode::fromString('FR'),
            CurrencyCode::fromString('EUR'),
            new \DateTimeImmutable('2026-01-01T10:00:00Z'),
        );
    }
}
