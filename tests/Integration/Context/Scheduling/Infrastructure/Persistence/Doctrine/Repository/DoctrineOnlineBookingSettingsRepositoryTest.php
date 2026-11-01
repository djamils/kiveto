<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Scheduling\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Scheduling\Domain\Booking\OnlineBookingSettings;
use App\Context\Scheduling\Domain\Booking\OnlineBookingSettingsRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineOnlineBookingSettingsRepositoryTest extends KernelTestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-00000000c001';

    private OnlineBookingSettingsRepositoryInterface $repository;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        $repository = self::getContainer()->get(OnlineBookingSettingsRepositoryInterface::class);
        \assert($repository instanceof OnlineBookingSettingsRepositoryInterface);
        $this->repository = $repository;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $this->entityManager = $entityManager;
    }

    public function testAClinicWithoutRowGetsTheDefaults(): void
    {
        self::assertSame(
            $this->snapshot(OnlineBookingSettings::defaults(ClinicId::fromString(self::CLINIC))),
            $this->snapshot($this->repository->forClinic(ClinicId::fromString(self::CLINIC))),
        );
    }

    public function testSaveInsertsThenUpdatesTheRow(): void
    {
        $clinicId = ClinicId::fromString(self::CLINIC);

        $this->repository->save(new OnlineBookingSettings($clinicId, true, 10, 0, 3, 180, 0, true));
        $this->entityManager->clear();

        $loaded = $this->repository->forClinic($clinicId);
        self::assertSame(self::CLINIC, $loaded->clinicId()->toString());
        self::assertTrue($loaded->isEnabled());
        self::assertSame(10, $loaded->gridStepMinutes());
        self::assertSame(0, $loaded->minLeadTimeMinutes());
        self::assertSame(3, $loaded->minLeadTimeManualHours());
        self::assertSame(180, $loaded->horizonDays());
        self::assertSame(0, $loaded->cancellationWindowHours());
        self::assertTrue($loaded->isNewClientBookingEnabled());

        $this->repository->save(new OnlineBookingSettings($clinicId, false, 30, 2880, 168, 1, 168, false));
        $this->entityManager->clear();

        self::assertSame($this->snapshot(new OnlineBookingSettings($clinicId, false, 30, 2880, 168, 1, 168, false)), $this->snapshot($this->repository->forClinic($clinicId)));
    }

    /**
     * @return list<bool|int|string>
     */
    private function snapshot(OnlineBookingSettings $settings): array
    {
        return [
            $settings->clinicId()->toString(),
            $settings->isEnabled(),
            $settings->gridStepMinutes(),
            $settings->minLeadTimeMinutes(),
            $settings->minLeadTimeManualHours(),
            $settings->horizonDays(),
            $settings->cancellationWindowHours(),
            $settings->isNewClientBookingEnabled(),
        ];
    }
}
