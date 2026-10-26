<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Clinic\Application\Command\Staff\RecordVeterinarianDailyCounts;

use App\Context\Clinic\Application\Command\Staff\RecordVeterinarianDailyCounts\RecordVeterinarianDailyCounts;
use App\Context\Clinic\Application\Port\VeterinarianDailyCountStoreInterface;
use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Entity\VeterinarianDailyCountEntity;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicMembershipEntityFactory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/**
 * D6 end to end: the active veterinarians of a clinic are counted (an
 * invited one who never activated does not count), the day keeps its
 * highest count, and Notification receives its copy.
 */
final class RecordVeterinarianDailyCountsTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID = '018f1b1e-dddd-7890-abcd-0123456789ab';

    public function testTheActiveVeterinariansAreCountedAndPushedToNotification(): void
    {
        ClinicEntityFactory::createOne(['id' => Uuid::fromString(self::CLINIC_ID), 'slug' => 'vet-count']);

        foreach ([UserStatus::ACTIVE, UserStatus::ACTIVE, UserStatus::PENDING] as $status) {
            $user = ClinicUserFactory::createOne(['status' => $status]);
            ClinicMembershipEntityFactory::new()
                ->withClinicId(self::CLINIC_ID)
                ->withUserId($user->getId()->toRfc4122())
                ->asVeterinary()
                ->asEmployee()
                ->create(['validFrom' => new \DateTimeImmutable('2026-01-01')])
            ;
        }

        $bus = static::getContainer()->get(CommandBusInterface::class);
        \assert($bus instanceof CommandBusInterface);
        $bus->dispatch(new RecordVeterinarianDailyCounts(self::CLINIC_ID));

        $clock = static::getContainer()->get(ClockInterface::class);
        \assert($clock instanceof ClockInterface);
        $day = $clock->now()->setTimezone(new \DateTimeZone('Europe/Paris'))->format('Y-m-d');

        self::assertSame(2, $this->storedCount('clinic__veterinarian_daily_counts', $day));
        self::assertSame(2, $this->storedCount('notification__veterinarian_counts', $day));
    }

    public function testTheDayKeepsItsHighestCount(): void
    {
        $store = static::getContainer()->get(VeterinarianDailyCountStoreInterface::class);
        \assert($store instanceof VeterinarianDailyCountStoreInterface);
        $day = new \DateTimeImmutable('2026-10-30');

        self::assertSame(3, $store->record(self::CLINIC_ID, $day, 3));
        self::assertSame(3, $store->record(self::CLINIC_ID, $day, 2));
        self::assertSame(4, $store->record(self::CLINIC_ID, $day, 4));
        self::assertSame(0, $store->record(self::CLINIC_ID, new \DateTimeImmutable('2026-10-31'), -1));
    }

    public function testTheEntityMapsTheTable(): void
    {
        $entity = new VeterinarianDailyCountEntity(Uuid::fromString(self::CLINIC_ID), new \DateTimeImmutable('2026-10-30'), 3);

        self::assertSame(self::CLINIC_ID, $entity->getClinicId()->toRfc4122());
        self::assertSame('2026-10-30', $entity->getDay()->format('Y-m-d'));
        self::assertSame(3, $entity->getCount());
    }

    private function storedCount(string $table, string $day): ?int
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        $count = $connection->fetchOne(
            'SELECT count FROM ' . $table . ' WHERE clinic_id = :clinic AND day = :day',
            ['clinic' => Uuid::fromString(self::CLINIC_ID)->toBinary(), 'day' => $day],
        );

        return is_numeric($count) ? (int) $count : null;
    }
}
