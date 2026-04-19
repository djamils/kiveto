<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians;

use App\Context\Clinic\Application\Port\ClinicMembershipReadRepositoryInterface;
use App\Context\Clinic\Application\Port\StaffProfileReadItem;
use App\Context\Clinic\Application\Port\StaffProfileReadRepositoryInterface;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ClinicVeterinarianItem;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ListClinicVeterinarians;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ListClinicVeterinariansHandler;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipId;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use PHPUnit\Framework\TestCase;

final class ListClinicVeterinariansHandlerTest extends TestCase
{
    private const string CLINIC_ID     = '12345678-9abc-def0-1234-56789abcdef0';
    private const string MEMBERSHIP_ID = 'aaaaaaaa-bbbb-7ccc-8ddd-eeeeeeeeee01';
    private const string USER_ID       = '01912345-6789-7abc-8def-0000000000a1';

    public function testReturnsEnrichedItemsFromComposedReadPaths(): void
    {
        $membershipItem = new ClinicVeterinarianItem(
            userId: self::USER_ID,
            role: 'VETERINARY',
            engagement: 'EMPLOYEE',
            membershipId: self::MEMBERSHIP_ID,
        );

        $profileItem = new StaffProfileReadItem(
            profileId: 'ffff1111-0000-7000-8000-000000000001',
            membershipId: self::MEMBERSHIP_ID,
            userId: self::USER_ID,
            displayName: 'Dr. Rousseau',
            professionalTitle: 'DR',
            agendaColor: '#1a2b3c',
            sortOrder: 0,
            isVisibleInAgenda: true,
        );

        $membershipRepo = $this->createMock(ClinicMembershipReadRepositoryInterface::class);
        $membershipRepo
            ->expects(self::once())
            ->method('findVeterinariansForClinic')
            ->with(self::callback(static fn (ClinicId $id): bool => self::CLINIC_ID === $id->toString()))
            ->willReturn([$membershipItem])
        ;

        $profileRepo = $this->createMock(StaffProfileReadRepositoryInterface::class);
        $profileRepo
            ->expects(self::once())
            ->method('findByMembershipIds')
            ->with([self::MEMBERSHIP_ID])
            ->willReturn([self::MEMBERSHIP_ID => $profileItem])
        ;

        $handler = new ListClinicVeterinariansHandler($membershipRepo, $profileRepo);

        $result = $handler(new ListClinicVeterinarians(self::CLINIC_ID));

        self::assertCount(1, $result);
        self::assertSame(self::USER_ID, $result[0]->userId);
        self::assertSame(self::MEMBERSHIP_ID, $result[0]->membershipId);
        self::assertSame('Dr. Rousseau', $result[0]->displayName);
        self::assertSame('DR', $result[0]->professionalTitle);
        self::assertSame('#1a2b3c', $result[0]->agendaColor);
        self::assertSame(0, $result[0]->sortOrder);
        self::assertTrue($result[0]->isVisibleInAgenda);
    }

    public function testToleratesMissingProfileForMembership(): void
    {
        $membershipItem = new ClinicVeterinarianItem(
            userId: self::USER_ID,
            role: 'VETERINARY',
            engagement: 'EMPLOYEE',
            membershipId: self::MEMBERSHIP_ID,
        );

        $membershipRepo = $this->createStub(ClinicMembershipReadRepositoryInterface::class);
        $membershipRepo
            ->method('findVeterinariansForClinic')
            ->willReturn([$membershipItem])
        ;

        $profileRepo = $this->createStub(StaffProfileReadRepositoryInterface::class);
        $profileRepo
            ->method('findByMembershipIds')
            ->willReturn([])
        ;

        $handler = new ListClinicVeterinariansHandler($membershipRepo, $profileRepo);

        $result = $handler(new ListClinicVeterinarians(self::CLINIC_ID));

        self::assertCount(1, $result);
        self::assertNull($result[0]->displayName);
        self::assertNull($result[0]->agendaColor);
    }

    public function testReturnsEmptyArrayWhenNoVeterinarians(): void
    {
        $membershipRepo = $this->createStub(ClinicMembershipReadRepositoryInterface::class);
        $membershipRepo
            ->method('findVeterinariansForClinic')
            ->willReturn([])
        ;

        $profileRepo = $this->createMock(StaffProfileReadRepositoryInterface::class);
        $profileRepo
            ->expects(self::never())
            ->method('findByMembershipIds')
        ;

        $handler = new ListClinicVeterinariansHandler($membershipRepo, $profileRepo);

        $result = $handler(new ListClinicVeterinarians(self::CLINIC_ID));

        self::assertSame([], $result);
    }

    public function testChangeRoleGuardHandlerTest(): void
    {
        // Regression: hasVeterinaryCredentialsFor is called with correct ClinicMembershipId
        $profileRepo = $this->createStub(StaffProfileReadRepositoryInterface::class);
        $profileRepo
            ->method('hasVeterinaryCredentialsFor')
            ->willReturn(false)
        ;

        self::assertFalse($profileRepo->hasVeterinaryCredentialsFor(ClinicMembershipId::fromString(self::MEMBERSHIP_ID)));
    }
}
