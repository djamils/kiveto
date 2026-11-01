<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\ClinicListing\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\ClinicListing\Domain\ClinicListing;
use App\Context\ClinicListing\Domain\ClinicListingId;
use App\Context\ClinicListing\Domain\ListingAddress;
use App\Context\ClinicListing\Domain\ListingCoordinates;
use App\Context\ClinicListing\Domain\ListingProfile;
use App\Context\ClinicListing\Domain\ListingSettings;
use App\Context\ClinicListing\Domain\Repository\ClinicListingRepositoryInterface;
use App\Context\ClinicListing\Domain\Repository\ListingSettingsRepositoryInterface;
use App\Context\ClinicListing\Infrastructure\Persistence\Doctrine\Entity\ClinicListingEntity;
use App\Context\ClinicListing\Infrastructure\Persistence\Doctrine\Entity\ListingSettingsEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DbalClinicListingRepositoryTest extends KernelTestCase
{
    private const string ID     = '01960000-0000-7000-8000-0000000b1001';
    private const string CLINIC = '01960000-0000-7000-8000-0000000b1c01';

    public function testAListingIsWrittenAndReadInFull(): void
    {
        $repository = self::listings();
        $profile    = new ListingProfile(
            name: 'Clinique du Parc',
            address: ListingAddress::fromParts('18 rue des Abbesses, Bâtiment B', '75018', 'Paris'),
            phone: '+33142551890',
            timezone: 'Europe/Paris',
            openingHours: [['day' => 1, 'opensAt' => '09:00', 'closesAt' => '12:30'], ['day' => 6, 'opensAt' => '09:00', 'closesAt' => '13:00']],
            active: true,
        );
        $listing = ClinicListing::project(ClinicListingId::fromString(self::ID), self::CLINIC, $profile, new \DateTimeImmutable('2026-11-02 10:00:00', new \DateTimeZone('Europe/Paris')));
        $listing->attachCoordinates((string) $listing->addressHash(), ListingCoordinates::fromDegrees(48.884612, -2.338123));
        $repository->save($listing);

        $read = $repository->findByClinicId(self::CLINIC);

        self::assertNotNull($read);
        self::assertSame([self::ID, self::CLINIC, $listing->addressHash(), '2026-11-02 09:00:00'], [$read->id()->toString(), $read->clinicId(), $read->addressHash(), $read->refreshedAt()->format('Y-m-d H:i:s')]);
        self::assertSame([48.884612, -2.338123], [$read->coordinates()?->latitude(), $read->coordinates()?->longitude()]);
        $readProfile = $read->profile();
        self::assertSame(['Clinique du Parc', '+33142551890', 'Europe/Paris', true, $profile->openingHours()], [$readProfile->name(), $readProfile->phone(), $readProfile->timezone(), $readProfile->isActive(), $readProfile->openingHours()]);
        self::assertSame(['18 rue des Abbesses, Bâtiment B', '75018', 'Paris'], [$readProfile->address()?->line(), $readProfile->address()?->postalCode(), $readProfile->address()?->city()]);

        self::assertNull($repository->findByClinicId('01960000-0000-7000-8000-0000000b1cff'));
    }

    public function testASecondSaveRewritesTheRowAndKeepsTheListingId(): void
    {
        $repository = self::listings();
        $listing    = ClinicListing::project(ClinicListingId::fromString(self::ID), self::CLINIC, new ListingProfile('Clinique du Parc', null, null, 'Europe/Paris', [], true), new \DateTimeImmutable('2026-11-02 09:00:00'));
        $repository->save($listing);

        $listing->refresh(new ListingProfile('Clinique du Lac', null, null, 'Indian/Reunion', [], false), new \DateTimeImmutable('2026-11-03 09:00:00'));
        $repository->save(ClinicListing::reconstitute(ClinicListingId::fromString('01960000-0000-7000-8000-0000000b1002'), self::CLINIC, $listing->profile(), null, null, $listing->refreshedAt()));

        $read = $repository->findByClinicId(self::CLINIC);
        self::assertNotNull($read);
        self::assertSame([self::ID, 'Clinique du Lac', 'Indian/Reunion', false, null, null, null], [
            $read->id()->toString(), $read->profile()->name(), $read->profile()->timezone(), $read->isActive(), $read->profile()->address(), $read->profile()->phone(), $read->coordinates(),
        ]);
    }

    public function testTheSettingsDefaultUntilSavedThenFollowEachSave(): void
    {
        $repository = self::settings();

        $defaults = $repository->forClinic(self::CLINIC);
        self::assertSame([self::CLINIC, false, true, []], [$defaults->clinicId(), $defaults->isListed(), $defaults->acceptsNewClients(), $defaults->tags()]);

        $repository->save(ListingSettings::reconstitute(self::CLINIC, true, false, ['NAC', 'Équidés']));
        $saved = $repository->forClinic(self::CLINIC);
        self::assertSame([true, false, ['NAC', 'Équidés']], [$saved->isListed(), $saved->acceptsNewClients(), $saved->tags()]);

        $saved->update(false, true, []);
        $repository->save($saved);
        $again = $repository->forClinic(self::CLINIC);
        self::assertSame([false, true, []], [$again->isListed(), $again->acceptsNewClients(), $again->tags()]);
    }

    public function testTheOrmSchemaReadsTheRowsTheSqlWrites(): void
    {
        $listing = ClinicListing::project(ClinicListingId::fromString(self::ID), self::CLINIC, new ListingProfile('Clinique du Parc', ListingAddress::fromParts('18 rue des Abbesses', '75018', 'Paris'), null, 'Europe/Paris', [], true), new \DateTimeImmutable('2026-11-02 09:00:00'));
        $listing->attachCoordinates((string) $listing->addressHash(), ListingCoordinates::fromDegrees(48.884612, 2.338123));
        self::listings()->save($listing);
        self::settings()->save(ListingSettings::reconstitute(self::CLINIC, true, true, ['NAC']));

        $em = self::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);

        $row = $em->find(ClinicListingEntity::class, Uuid::fromString(self::CLINIC));
        self::assertInstanceOf(ClinicListingEntity::class, $row);
        self::assertSame([self::ID, 'Paris', '48.884612', '2.338123', true], [$row->id->toRfc4122(), $row->city, $row->lat, $row->lng, $row->active]);

        $settings = $em->find(ListingSettingsEntity::class, Uuid::fromString(self::CLINIC));
        self::assertInstanceOf(ListingSettingsEntity::class, $settings);
        self::assertSame([true, true, ['NAC']], [$settings->listed, $settings->acceptsNewClients, $settings->tags]);

        $built = new ClinicListingEntity(Uuid::v7(), Uuid::v7(), 'A', null, null, null, null, null, null, null, 'UTC', [], false, new \DateTimeImmutable());
        self::assertSame('UTC', $built->timezone);
        self::assertSame([], new ListingSettingsEntity(Uuid::v7(), false, true, [])->tags);
    }

    private static function listings(): ClinicListingRepositoryInterface
    {
        $repository = self::getContainer()->get(ClinicListingRepositoryInterface::class);
        \assert($repository instanceof ClinicListingRepositoryInterface);

        return $repository;
    }

    private static function settings(): ListingSettingsRepositoryInterface
    {
        $repository = self::getContainer()->get(ListingSettingsRepositoryInterface::class);
        \assert($repository instanceof ListingSettingsRepositoryInterface);

        return $repository;
    }
}
