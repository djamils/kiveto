<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Scheduling\Infrastructure\Adapter;

use App\Context\Scheduling\Application\Port\PractitionerDayLockInterface;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PractitionerDay;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Entity\PractitionerDayLockEntity;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * One row per practitioner-day, reused by every later lock of that day, and
 * purged once the day is old.
 */
final class DbalPractitionerDayLockTest extends KernelTestCase
{
    private const string CLINIC   = '01960000-0000-7000-8000-0000000c1f01';
    private const string ROUSSEAU = '01960000-0000-7000-8000-0000000d1f01';
    private const string LAMBERT  = '01960000-0000-7000-8000-0000000d1f02';

    public function testLockingUpsertsOneRowPerPractitionerDay(): void
    {
        $locks  = $this->locks();
        $clinic = ClinicId::fromString(self::CLINIC);

        $locks->lock($clinic, [$this->day(self::ROUSSEAU, '2026-03-11'), $this->day(self::ROUSSEAU, '2026-03-12'), $this->day(self::ROUSSEAU, '2026-03-11')]);
        $locks->lock($clinic, [$this->day(self::ROUSSEAU, '2026-03-12'), $this->day(self::LAMBERT, '2026-03-12')]);

        self::assertSame(
            [self::ROUSSEAU . ' 2026-03-11', self::ROUSSEAU . ' 2026-03-12', self::LAMBERT . ' 2026-03-12'],
            $this->rows(),
        );
    }

    public function testThePurgeDropsTheDaysBeforeTheCutoff(): void
    {
        $locks = $this->locks();
        $locks->lock(ClinicId::fromString(self::CLINIC), [$this->day(self::ROUSSEAU, '2026-09-30'), $this->day(self::ROUSSEAU, '2026-10-01'), $this->day(self::ROUSSEAU, '2026-10-02')]);

        self::assertSame(1, $locks->purgeBefore('2026-10-01'));
        self::assertSame([self::ROUSSEAU . ' 2026-10-01', self::ROUSSEAU . ' 2026-10-02'], $this->rows());
    }

    public function testTheEntityOnlyDescribesTheTable(): void
    {
        $id  = Uuid::fromString(self::CLINIC);
        $day = new \DateTimeImmutable('2026-03-11');

        self::assertSame(['clinic_id' => $id, 'practitioner_user_id' => $id, 'local_date' => $day], new PractitionerDayLockEntity($id, $id, $day)->columns());
    }

    private function locks(): PractitionerDayLockInterface
    {
        $locks = self::getContainer()->get(PractitionerDayLockInterface::class);
        \assert($locks instanceof PractitionerDayLockInterface);

        return $locks;
    }

    private function day(string $practitioner, string $date): PractitionerDay
    {
        return new PractitionerDay(UserId::fromString($practitioner), $date);
    }

    /**
     * @return list<string>
     */
    private function rows(): array
    {
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        \assert($connection instanceof Connection);

        $rows = $connection->fetchAllAssociative(
            'SELECT practitioner_user_id, local_date FROM scheduling__practitioner_day_locks WHERE clinic_id = ? ORDER BY practitioner_user_id, local_date',
            [Uuid::fromString(self::CLINIC)->toBinary()],
        );

        return array_map(static function (array $row): string {
            \assert(\is_string($row['practitioner_user_id']) && \is_string($row['local_date']));

            return Uuid::fromBinary($row['practitioner_user_id'])->toRfc4122() . ' ' . $row['local_date'];
        }, $rows);
    }
}
