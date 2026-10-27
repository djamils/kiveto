<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Service;

use App\Context\Clinic\Application\Command\Clinic\SyncAllClinicMessagingProfiles\SyncAllClinicMessagingProfiles;
use App\Context\Clinic\Application\Command\Clinic\SyncAllClinicMessagingProfiles\SyncAllClinicMessagingProfilesHandler;
use App\Context\Clinic\Application\Command\Staff\RecordVeterinarianDailyCounts\RecordVeterinarianDailyCounts;
use App\Context\Clinic\Application\Port\ClinicMessagingProfileData;
use App\Context\Clinic\Application\Port\ClinicReadRepositoryInterface;
use App\Context\Clinic\Application\Port\MessagingProfileSyncInterface;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\ListClinics\ClinicCollection;
use App\Context\Clinic\Application\Service\MessagingProfilePublisher;
use App\Context\Clinic\Domain\Clinic;
use App\Context\Clinic\Domain\Event\ClinicBrandingChanged;
use App\Context\Clinic\Domain\Event\ClinicCreated;
use App\Context\Clinic\Domain\Event\ClinicLetterheadUpdated;
use App\Context\Clinic\Domain\Event\ClinicMessagingSettingsChanged;
use App\Context\Clinic\Domain\Event\ClinicRenamed;
use App\Context\Clinic\Domain\Event\ClinicSlugChanged;
use App\Context\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Context\Clinic\Domain\Staff\ValueObject\HexColor;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Context\Clinic\Domain\ValueObject\ClinicSlug;
use App\Context\Clinic\Domain\ValueObject\ClinicStatus;
use App\Context\Clinic\Domain\ValueObject\Letterhead;
use App\Context\Clinic\Domain\ValueObject\MessagingSettings;
use App\Context\Clinic\Infrastructure\Adapter\Notification\ClinicMessagingProfileBridge;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\Localization\Locale;
use App\Shared\Domain\Localization\TimeZone;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\PostalAddress;
use PHPUnit\Framework\TestCase;

final class MessagingProfilePublisherTest extends TestCase
{
    private const string CLINIC_ID = '018f1b1e-1234-7890-abcd-0123456789ab';

    /** @var list<ClinicMessagingProfileData> */
    private array $pushed = [];

    public function testTheWholeProfileIsPushed(): void
    {
        $clinic = self::clinic();
        $at     = new \DateTimeImmutable('2026-10-01');
        $clinic->updateLetterhead(Letterhead::create(
            address: PostalAddress::create(streetLine1: '18 rue Lepic', city: 'Paris', countryCode: 'FR', postalCode: '75018'),
            phone: '+33142641800',
            email: 'contact@montmartre.fr',
        ), $at);
        $clinic->changeBrandColor(HexColor::fromString('#4f86c6'), $at);
        $clinic->changeLogo('clinic/logo.png', $at);
        $clinic->changeMessagingSettings(MessagingSettings::create('Montmartre', 300), $at);

        $this->publisher($clinic)->publish(self::CLINIC_ID);

        self::assertSame([(array) new ClinicMessagingProfileData(
            clinicId: self::CLINIC_ID,
            name: 'Clinique Montmartre',
            emailLocalPart: 'clinique-montmartre',
            replyTo: 'contact@montmartre.fr',
            smsSender: 'Montmartre',
            overageCapSegments: 300,
            addressLine: '18 rue Lepic, 75018 Paris',
            city: 'Paris',
            phone: '+33142641800',
            brandColor: '#4f86c6',
            logoStorageKey: 'clinic/logo.png',
        )], array_map(static fn (ClinicMessagingProfileData $d): array => (array) $d, $this->pushed));
    }

    public function testAClinicWithoutLetterheadGetsTheDefaultsAndEmptyCoordinates(): void
    {
        $this->publisher(self::clinic())->publish(self::CLINIC_ID);

        self::assertCount(1, $this->pushed);
        self::assertSame('Clinique Mo', $this->pushed[0]->smsSender);
        self::assertSame(500, $this->pushed[0]->overageCapSegments);
        self::assertSame('', $this->pushed[0]->addressLine);
        self::assertSame('', $this->pushed[0]->city);
        self::assertNull($this->pushed[0]->replyTo);
        self::assertNull($this->pushed[0]->phone);
    }

    public function testAnUnknownClinicPushesNothing(): void
    {
        $this->publisher(null)->publish(self::CLINIC_ID);

        self::assertSame([], $this->pushed);
    }

    public function testEveryProfileEventPushesTheProfile(): void
    {
        $bridge = new ClinicMessagingProfileBridge($this->publisher(self::clinic()));

        $bridge->onCreated(new ClinicCreated(self::CLINIC_ID, 'n', 's', 'Europe/Paris', 'fr-FR', null));
        $bridge->onRenamed(new ClinicRenamed(self::CLINIC_ID, 'n'));
        $bridge->onSlugChanged(new ClinicSlugChanged(self::CLINIC_ID, 's'));
        $bridge->onLetterheadUpdated(new ClinicLetterheadUpdated(self::CLINIC_ID, false));
        $bridge->onBrandingChanged(new ClinicBrandingChanged(self::CLINIC_ID, null, false));
        $bridge->onMessagingSettingsChanged(new ClinicMessagingSettingsChanged(self::CLINIC_ID, 'Lilas', 500));

        self::assertCount(6, $this->pushed);
    }

    public function testSyncingAllPushesEveryProfileThenCountsTheVeterinarians(): void
    {
        $read = $this->createStub(ClinicReadRepositoryInterface::class);
        $read->method('findAllFiltered')->willReturn(new ClinicCollection([
            new ClinicDto(self::CLINIC_ID, 'C', 'c', 'Europe/Paris', 'fr-FR', 'FR', null, 'EUR', ClinicStatus::ACTIVE, null, '2026-01-01', '2026-01-01'),
            new ClinicDto(self::CLINIC_ID, 'C', 'c', 'Europe/Paris', 'fr-FR', 'FR', null, 'EUR', ClinicStatus::ACTIVE, null, '2026-01-01', '2026-01-01'),
        ], 2));

        $bus = $this->createMock(CommandBusInterface::class);
        // Counted synchronously, clinic by clinic: the fixtures read the quotas on return.
        $bus->expects(self::exactly(2))->method('dispatch')->with(new RecordVeterinarianDailyCounts(self::CLINIC_ID));

        $handler = new SyncAllClinicMessagingProfilesHandler($read, $this->publisher(self::clinic()), $bus);

        self::assertSame(2, $handler(new SyncAllClinicMessagingProfiles()));
        self::assertCount(2, $this->pushed);
    }

    private function publisher(?Clinic $clinic): MessagingProfilePublisher
    {
        $repository = $this->createStub(ClinicRepositoryInterface::class);
        $repository->method('findById')->willReturn($clinic);

        $sync = $this->createStub(MessagingProfileSyncInterface::class);
        $sync->method('pushProfile')->willReturnCallback(function (ClinicMessagingProfileData $data): void {
            $this->pushed[] = $data;
        });

        return new MessagingProfilePublisher($repository, $sync);
    }

    private static function clinic(): Clinic
    {
        return Clinic::create(
            ClinicId::fromString(self::CLINIC_ID),
            'Clinique Montmartre',
            ClinicSlug::fromString('clinique-montmartre'),
            TimeZone::fromString('Europe/Paris'),
            Locale::fromString('fr-FR'),
            CountryCode::fromString('FR'),
            CurrencyCode::fromString('EUR'),
            new \DateTimeImmutable('2026-01-01T10:00:00Z'),
        );
    }
}
