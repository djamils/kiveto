<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Admission;

use App\Context\Admission\Application\Port\AdmissionReadRepositoryInterface;
use App\Context\Admission\Application\Port\WaitingRoomItemDto;
use App\Context\Admission\Application\Query\ListActiveAdmissions\ListActiveAdmissions;
use App\Context\Admission\Application\Query\ListActiveAdmissions\ListActiveAdmissionsHandler;
use App\Context\Admission\Application\Query\ListAdmissionsClosedSince\ListAdmissionsClosedSince;
use App\Context\Admission\Application\Query\ListAdmissionsClosedSince\ListAdmissionsClosedSinceHandler;
use PHPUnit\Framework\TestCase;

final class ListAdmissionsQueriesHandlerTest extends TestCase
{
    private const string CLINIC_ID = '22222222-2222-4222-8222-222222222222';

    public function testListsEveryActiveAdmissionOfTheClinic(): void
    {
        $item       = $this->item('in_hospitalization');
        $repository = $this->createMock(AdmissionReadRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('findAllActiveForClinic')
            ->with(self::CLINIC_ID)
            ->willReturn([$item])
        ;

        $handler = new ListActiveAdmissionsHandler($repository);

        self::assertSame([$item], $handler(new ListActiveAdmissions(self::CLINIC_ID)));
    }

    public function testListsTheAdmissionsClosedSinceTheGivenInstant(): void
    {
        $since      = new \DateTimeImmutable('2026-10-14 00:00:00');
        $item       = $this->item('at_reception');
        $repository = $this->createMock(AdmissionReadRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('findClosedForClinicSince')
            ->with(self::CLINIC_ID, $since)
            ->willReturn([$item])
        ;

        $handler = new ListAdmissionsClosedSinceHandler($repository);

        self::assertSame([$item], $handler(new ListAdmissionsClosedSince(self::CLINIC_ID, $since)));
    }

    private function item(string $location): WaitingRoomItemDto
    {
        return new WaitingRoomItemDto(
            admissionId: '44444444-4444-4444-8444-444444444444',
            clinicId: self::CLINIC_ID,
            patientId: '66666666-6666-4666-8666-666666666666',
            displayLabel: 'Rex',
            triageLevel: 'standard',
            locationStatus: $location,
            intakeChannel: 'walk_in',
            openedAt: '2026-10-14T08:00:00+00:00',
            triageNotes: null,
        );
    }
}
