<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Command\Staff\RecordVeterinarianDailyCounts;

use App\Context\Clinic\Application\Command\Staff\RecordVeterinarianDailyCounts\RecordVeterinarianDailyCounts;
use App\Context\Clinic\Application\Command\Staff\RecordVeterinarianDailyCounts\RecordVeterinarianDailyCountsHandler;
use App\Context\Clinic\Application\Port\ClinicReadRepositoryInterface;
use App\Context\Clinic\Application\Port\MessagingProfileSyncInterface;
use App\Context\Clinic\Application\Port\UserAccountStatusInterface;
use App\Context\Clinic\Application\Port\VeterinarianCountJobsInterface;
use App\Context\Clinic\Application\Port\VeterinarianDailyCountStoreInterface;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\ListClinics\ClinicCollection;
use App\Context\Clinic\Domain\Staff\Repository\ClinicMembershipRepositoryInterface;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Context\Clinic\Domain\ValueObject\ClinicStatus;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\TestCase;

final class RecordVeterinarianDailyCountsHandlerTest extends TestCase
{
    private const string A = '018f1b1e-1234-7890-abcd-0000000000a1';
    private const string B = '018f1b1e-1234-7890-abcd-0000000000b1';
    private const string C = '018f1b1e-1234-7890-abcd-0000000000c1';

    /** @var list<string> */
    private array $queued = [];

    /** @var list<array{string, string, int}> */
    private array $recorded = [];

    /** @var list<array{string, string, int}> */
    private array $pushed = [];

    /**
     * AC 33: an invited veterinarian who never activated does not count; the
     * day is the Paris day (23:30 UTC on the 26th is the 27th in Paris).
     */
    public function testOnlyActiveAccountsCountAndTheStoredMaximumIsPushed(): void
    {
        $handler = $this->handler(
            veterinarians: [self::A => ['v1', 'v2', 'pending']],
            stored: 5,
        );

        $handler(new RecordVeterinarianDailyCounts(self::A));

        self::assertSame([[self::A, '2026-10-27', 2]], $this->recorded);
        self::assertSame([[self::A, '2026-10-27', 5]], $this->pushed);
    }

    public function testAClinicWithoutVeterinarianCountsZero(): void
    {
        $this->handler(veterinarians: [self::B => []], stored: null)(new RecordVeterinarianDailyCounts(self::B));

        self::assertSame([[self::B, '2026-10-27', 0]], $this->recorded);
        self::assertSame([[self::B, '2026-10-27', 0]], $this->pushed);
    }

    /**
     * The nightly run counts nothing itself: one job per clinic that is not
     * CLOSED, each in its own transaction on the worker.
     */
    public function testWithoutAnIdEachOpenClinicGetsAJobOfItsOwn(): void
    {
        $handler = $this->handler(
            veterinarians: [],
            stored: null,
            clinics: [self::A => ClinicStatus::ACTIVE, self::C => ClinicStatus::CLOSED, self::B => ClinicStatus::SUSPENDED],
        );

        $handler(new RecordVeterinarianDailyCounts());

        self::assertSame([self::A, self::B], $this->queued);
        self::assertSame([], $this->recorded);
        self::assertSame([], $this->pushed);
    }

    /**
     * @param array<string, list<string>> $veterinarians
     * @param array<string, ClinicStatus> $clinics
     */
    private function handler(array $veterinarians, ?int $stored, array $clinics = []): RecordVeterinarianDailyCountsHandler
    {
        $clinicRead = $this->createStub(ClinicReadRepositoryInterface::class);
        $clinicRead->method('findAllFiltered')->willReturn(new ClinicCollection(
            array_map(static fn (string $id, ClinicStatus $status): ClinicDto => new ClinicDto($id, 'C', 'c', 'Europe/Paris', 'fr-FR', 'FR', null, 'EUR', $status, null, '2026-01-01', '2026-01-01'), array_keys($clinics), $clinics),
            \count($clinics),
        ));

        $jobs = $this->createStub(VeterinarianCountJobsInterface::class);
        $jobs->method('countLater')->willReturnCallback(function (string $clinicId): void {
            $this->queued[] = $clinicId;
        });

        $memberships = $this->createStub(ClinicMembershipRepositoryInterface::class);
        $memberships->method('findEffectiveUserIds')->willReturnCallback(
            static function (ClinicId $clinicId, \DateTimeImmutable $now, ?ClinicMemberRole $role) use ($veterinarians): array {
                self::assertSame(ClinicMemberRole::VETERINARY, $role);

                return $veterinarians[$clinicId->toString()];
            },
        );

        $accounts = $this->createMock(UserAccountStatusInterface::class);
        $accounts->expects(self::atMost(1))->method('activeUserIds')->willReturnCallback(
            static fn (array $ids): array => array_values(array_filter($ids, static fn (mixed $id): bool => 'pending' !== $id)),
        );

        $store = $this->createStub(VeterinarianDailyCountStoreInterface::class);
        $store->method('record')->willReturnCallback(function (string $clinicId, \DateTimeImmutable $day, int $count) use ($stored): int {
            $this->recorded[] = [$clinicId, $day->format('Y-m-d'), $count];

            return $stored ?? $count;
        });

        $sync = $this->createStub(MessagingProfileSyncInterface::class);
        $sync->method('pushVeterinarianCount')->willReturnCallback(function (string $clinicId, string $day, int $count): void {
            $this->pushed[] = [$clinicId, $day, $count];
        });

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-10-26T23:30:00Z'));

        return new RecordVeterinarianDailyCountsHandler($clinicRead, $memberships, $accounts, $store, $sync, $jobs, $clock);
    }
}
