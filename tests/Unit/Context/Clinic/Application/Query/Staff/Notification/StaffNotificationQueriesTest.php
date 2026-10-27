<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Query\Staff\Notification;

use App\Context\Clinic\Application\Port\StaffProfileReadItem;
use App\Context\Clinic\Application\Port\StaffProfileReadRepositoryInterface;
use App\Context\Clinic\Application\Port\UserAccountStatusInterface;
use App\Context\Clinic\Application\Query\Staff\GetStaffPrintedName\GetStaffPrintedName;
use App\Context\Clinic\Application\Query\Staff\GetStaffPrintedName\GetStaffPrintedNameHandler;
use App\Context\Clinic\Application\Query\Staff\ListClinicMembersForNotification\ListClinicMembersForNotification;
use App\Context\Clinic\Application\Query\Staff\ListClinicMembersForNotification\ListClinicMembersForNotificationHandler;
use App\Context\Clinic\Domain\Staff\ClinicMembership;
use App\Context\Clinic\Domain\Staff\Repository\ClinicMembershipRepositoryInterface;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipId;
use App\Context\Clinic\Domain\Staff\ValueObject\UserId;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StaffNotificationQueriesTest extends TestCase
{
    private const string CLINIC_ID     = '018f1b1e-1234-7890-abcd-0123456789ab';
    private const string USER_ID       = '018f1b1e-1234-7890-abcd-0123456789ac';
    private const string MEMBERSHIP_ID = '018f1b1e-1234-7890-abcd-0123456789ad';

    public function testMembersAreListedThroughTheRepositoryAtNow(): void
    {
        $now   = new \DateTimeImmutable('2026-10-26T10:00:00Z');
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($now);

        $memberships = $this->createMock(ClinicMembershipRepositoryInterface::class);
        $memberships->expects(self::exactly(2))->method('findEffectiveUserIds')
            ->willReturnCallback(static function (ClinicId $clinicId, \DateTimeImmutable $at, ?ClinicMemberRole $role, bool $administratorsOnly) use ($now): array {
                self::assertSame(self::CLINIC_ID, $clinicId->toString());
                self::assertSame($now, $at);

                return null === $role ? ['admin:' . ($administratorsOnly ? 'y' : 'n')] : [$role->value];
            })
        ;

        $handler = new ListClinicMembersForNotificationHandler($memberships, $this->allActive(), $clock);

        self::assertSame(['VETERINARY_ASSISTANT'], $handler(new ListClinicMembersForNotification(self::CLINIC_ID, 'VETERINARY_ASSISTANT')));
        self::assertSame(['admin:y'], $handler(new ListClinicMembersForNotification(self::CLINIC_ID, administratorsOnly: true)));
    }

    /**
     * An invited member who never activated their account is not told:
     * they could not open the bell anyway.
     */
    public function testInvitedMembersAreNotListed(): void
    {
        $memberships = $this->createStub(ClinicMembershipRepositoryInterface::class);
        $memberships->method('findEffectiveUserIds')->willReturnOnConsecutiveCalls(['active-1', 'invited', 'active-2'], []);

        $accounts = $this->createMock(UserAccountStatusInterface::class);
        $accounts->expects(self::once())->method('activeUserIds')->with(['active-1', 'invited', 'active-2'])->willReturn(['active-1', 'active-2']);

        $handler = new ListClinicMembersForNotificationHandler($memberships, $accounts, $this->createStub(ClockInterface::class));

        self::assertSame(['active-1', 'active-2'], $handler(new ListClinicMembersForNotification(self::CLINIC_ID)));
        self::assertSame([], $handler(new ListClinicMembersForNotification(self::CLINIC_ID)), 'nobody to ask about');
    }

    /**
     * D15: « Dr Claire Lambert », no dot.
     */
    #[DataProvider('provideThePrintedNameCases')]
    public function testThePrintedName(?string $title, string $first, string $last, string $display, string $expected): void
    {
        $profiles = $this->createStub(StaffProfileReadRepositoryInterface::class);
        $profiles->method('findByMembershipId')->willReturn(new StaffProfileReadItem(
            profileId: 'p',
            membershipId: self::MEMBERSHIP_ID,
            userId: self::USER_ID,
            displayName: $display,
            professionalTitle: $title,
            agendaColor: '#4f86c6',
            sortOrder: 0,
            isVisibleInAgenda: true,
            firstName: $first,
            lastName: $last,
        ));

        $handler = new GetStaffPrintedNameHandler($this->membershipsFinding(self::membership()), $profiles);

        self::assertSame($expected, $handler(new GetStaffPrintedName(self::CLINIC_ID, self::USER_ID)));
    }

    /**
     * @return iterable<string, array{?string, string, string, string, string}>
     */
    public static function provideThePrintedNameCases(): iterable
    {
        yield 'doctor' => ['DR', 'Claire', 'Lambert', 'Dr Lambert', 'Dr Claire Lambert'];
        yield 'professor' => ['PR', 'Paul', 'Roux', 'Roux', 'Pr Paul Roux'];
        yield 'no title' => [null, 'Hugo', 'Martin', 'Hugo', 'Hugo Martin'];
        yield 'title none' => ['NONE', 'Hugo', 'Martin', 'Hugo', 'Hugo Martin'];
        yield 'no names' => ['DR', '', '', 'Rousseau', 'Dr Rousseau'];
    }

    public function testNoMembershipOrNoProfileGivesNoName(): void
    {
        $profiles = $this->createStub(StaffProfileReadRepositoryInterface::class);
        $profiles->method('findByMembershipId')->willReturn(null);

        $query = new GetStaffPrintedName(self::CLINIC_ID, self::USER_ID);

        self::assertNull(new GetStaffPrintedNameHandler($this->membershipsFinding(null), $profiles)($query));
        self::assertNull(new GetStaffPrintedNameHandler($this->membershipsFinding(self::membership()), $profiles)($query));
    }

    private function allActive(): UserAccountStatusInterface
    {
        $accounts = $this->createStub(UserAccountStatusInterface::class);
        $accounts->method('activeUserIds')->willReturnArgument(0);

        return $accounts;
    }

    private function membershipsFinding(?ClinicMembership $membership): ClinicMembershipRepositoryInterface
    {
        $memberships = $this->createStub(ClinicMembershipRepositoryInterface::class);
        $memberships->method('findByClinicAndUser')->willReturn($membership);

        return $memberships;
    }

    private static function membership(): ClinicMembership
    {
        return ClinicMembership::create(
            id: ClinicMembershipId::fromString(self::MEMBERSHIP_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            userId: UserId::fromString(self::USER_ID),
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            validFrom: new \DateTimeImmutable('2026-01-01'),
            validUntil: null,
            createdAt: new \DateTimeImmutable('2026-01-01'),
            isClinicAdministrator: false,
        );
    }
}
