<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\FeatureCatalog\Infrastructure\Persistence\Doctrine\Repository;

use App\System\FeatureCatalog\Domain\ClinicFeature;
use App\System\FeatureCatalog\Domain\FeatureAudience;
use App\System\FeatureCatalog\Domain\FeatureCatalogue;
use App\System\FeatureCatalog\Domain\FeatureRepositoryInterface;
use App\System\FeatureCatalog\Domain\FeatureSettings;
use App\System\FeatureCatalog\Domain\FeatureStatus;
use App\System\FeatureCatalog\Infrastructure\Persistence\Doctrine\Entity\ClinicFeatureEntity;
use App\System\FeatureCatalog\Infrastructure\Persistence\Doctrine\Entity\FeatureSettingsEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DbalFeatureRepositoryTest extends KernelTestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-00000000f7a1';
    private const string OTHER  = '01960000-0000-7000-8000-00000000f7a2';
    private const string ACTOR  = '01960000-0000-7000-8000-00000000f7b1';

    public function testSavedSettingsAreReadBackAndReplaced(): void
    {
        $features = $this->features();

        self::assertNull($features->settings(FeatureCatalogue::ELECTRONIC_INVOICING), 'never saved');

        // A Paris time: the dates are stored in UTC and read back as UTC.
        $features->saveSettings(FeatureSettings::reconstitute(FeatureCatalogue::ELECTRONIC_INVOICING, FeatureStatus::AVAILABLE, true, new \DateTimeImmutable('2026-11-06 10:00:00', new \DateTimeZone('Europe/Paris'))));
        $saved = $features->settings(FeatureCatalogue::ELECTRONIC_INVOICING);
        self::assertNotNull($saved);
        self::assertSame([FeatureCatalogue::ELECTRONIC_INVOICING, FeatureStatus::AVAILABLE, true, '2026-11-06 09:00:00', 'UTC'], [
            $saved->code(), $saved->status(), $saved->isDefaultOn(), $saved->updatedAt()?->format('Y-m-d H:i:s'), $saved->updatedAt()?->getTimezone()->getName(),
        ]);

        $features->saveSettings(FeatureSettings::reconstitute(FeatureCatalogue::ELECTRONIC_INVOICING, FeatureStatus::BETA, false, null));
        $replaced = $features->settings(FeatureCatalogue::ELECTRONIC_INVOICING);
        self::assertSame([FeatureStatus::BETA, false, null, FeatureAudience::ALL, null], [$replaced?->status(), $replaced?->isDefaultOn(), $replaced?->updatedAt(), $replaced?->audience(), $replaced?->cutOff()]);
    }

    public function testTheAudienceAndTheCutOffAreReadBack(): void
    {
        $features = $this->features();
        $settings = FeatureSettings::reconstitute(FeatureCatalogue::SMS_REMINDERS, FeatureStatus::AVAILABLE, true, null, FeatureAudience::PAID);
        $settings->cut('Envois en double', 'Djamil Soidiki', new \DateTimeImmutable('2026-11-06 11:30:00', new \DateTimeZone('Europe/Paris')));
        $features->saveSettings($settings);

        $saved = $features->settings(FeatureCatalogue::SMS_REMINDERS);
        self::assertSame(
            [FeatureAudience::PAID, 'Envois en double', 'Djamil Soidiki', '2026-11-06 10:30:00'],
            [$saved?->audience(), $saved?->cutOff()?->reason, $saved?->cutOff()?->byName, $saved?->cutOff()?->at->format('Y-m-d H:i:s')],
        );

        $saved?->restore(new \DateTimeImmutable());
        \assert(null !== $saved);
        $features->saveSettings($saved);
        self::assertNull($features->settings(FeatureCatalogue::SMS_REMINDERS)?->cutOff(), 'restored');
    }

    public function testAClinicFeatureIsReadBackAndReplaced(): void
    {
        $features = $this->features();

        self::assertNull($features->clinicFeature(self::CLINIC, FeatureCatalogue::ONLINE_BOOKING));

        $features->saveClinicFeature(new ClinicFeature(self::CLINIC, FeatureCatalogue::ONLINE_BOOKING, true, new \DateTimeImmutable('2026-11-06 10:00:00', new \DateTimeZone('Europe/Paris')), self::ACTOR, 'Djamil Soidiki'));
        self::assertSame([self::CLINIC, FeatureCatalogue::ONLINE_BOOKING, true, '2026-11-06 09:00:00', 'UTC', self::ACTOR, 'Djamil Soidiki'], self::fields($features->clinicFeature(self::CLINIC, FeatureCatalogue::ONLINE_BOOKING)));

        $features->saveClinicFeature(new ClinicFeature(self::CLINIC, FeatureCatalogue::ONLINE_BOOKING, false, new \DateTimeImmutable('2026-11-07 09:00:00', new \DateTimeZone('UTC')), null, null));
        self::assertSame([self::CLINIC, FeatureCatalogue::ONLINE_BOOKING, false, '2026-11-07 09:00:00', 'UTC', null, null], self::fields($features->clinicFeature(self::CLINIC, FeatureCatalogue::ONLINE_BOOKING)));
        self::assertNull($features->clinicFeature(self::OTHER, FeatureCatalogue::ONLINE_BOOKING));
    }

    public function testTheClinicsOfAFeatureAndTheFeaturesOfAClinicAreListed(): void
    {
        $features = $this->features();
        $at       = new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC'));
        $features->saveClinicFeature(new ClinicFeature(self::CLINIC, FeatureCatalogue::SMS_REMINDERS, false, $at, null, 'Kiveto'));
        $features->saveClinicFeature(new ClinicFeature(self::CLINIC, FeatureCatalogue::ELECTRONIC_INVOICING, true, $at, null, 'Kiveto'));
        $features->saveClinicFeature(new ClinicFeature(self::OTHER, FeatureCatalogue::SMS_REMINDERS, true, $at, self::ACTOR, 'Djamil'));

        $clinics = array_map(static fn (ClinicFeature $feature): array => [$feature->clinicId, $feature->enabled], $features->clinicsOf(FeatureCatalogue::SMS_REMINDERS));
        sort($clinics);
        self::assertSame([[self::CLINIC, false], [self::OTHER, true]], $clinics);
        self::assertSame([], $features->clinicsOf(FeatureCatalogue::SUPPORT_WRITE_MODE));

        self::assertSame(
            [FeatureCatalogue::ELECTRONIC_INVOICING, FeatureCatalogue::SMS_REMINDERS],
            array_map(static fn (ClinicFeature $feature): string => $feature->code, $features->featuresOf(self::CLINIC)),
            'by code',
        );
        self::assertSame([], $features->featuresOf('01960000-0000-7000-8000-00000000f7ff'));
    }

    public function testTheEntitiesDescribeTheTables(): void
    {
        $features = $this->features();
        $features->saveSettings(FeatureSettings::reconstitute(FeatureCatalogue::SMS_REMINDERS, FeatureStatus::AVAILABLE, false, new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC'))));
        $features->saveClinicFeature(new ClinicFeature(self::CLINIC, FeatureCatalogue::SMS_REMINDERS, true, new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC')), self::ACTOR, 'Djamil'));

        $em = self::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);

        $settings = $em->find(FeatureSettingsEntity::class, FeatureCatalogue::SMS_REMINDERS);
        self::assertInstanceOf(FeatureSettingsEntity::class, $settings);
        self::assertSame(['available', false], [$settings->status, $settings->defaultOn]);

        $clinic = $em->find(ClinicFeatureEntity::class, ['clinicId' => Uuid::fromString(self::CLINIC), 'code' => FeatureCatalogue::SMS_REMINDERS]);
        self::assertInstanceOf(ClinicFeatureEntity::class, $clinic);
        self::assertSame([true, self::ACTOR, 'Djamil'], [$clinic->enabled, $clinic->changedById?->toRfc4122(), $clinic->changedByName]);
    }

    /**
     * @return list<mixed>
     */
    private static function fields(?ClinicFeature $feature): array
    {
        \assert(null !== $feature);

        return [
            $feature->clinicId, $feature->code, $feature->enabled, $feature->changedAt->format('Y-m-d H:i:s'), $feature->changedAt->getTimezone()->getName(),
            $feature->changedById, $feature->changedByName,
        ];
    }

    private function features(): FeatureRepositoryInterface
    {
        $repository = self::getContainer()->get(FeatureRepositoryInterface::class);
        \assert($repository instanceof FeatureRepositoryInterface);

        return $repository;
    }
}
