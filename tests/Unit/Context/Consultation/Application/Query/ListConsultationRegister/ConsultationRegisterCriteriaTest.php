<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Application\Query\ListConsultationRegister;

use App\Context\Consultation\Application\Query\ListConsultationRegister\ConsultationRegisterCriteria;
use App\Context\Consultation\Application\Query\ListConsultationRegister\ConsultationRegisterItem;
use App\Context\Consultation\Application\Query\ListConsultationRegister\ConsultationRegisterPage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConsultationRegisterCriteriaTest extends TestCase
{
    public function testOffsetFollowsThePage(): void
    {
        $criteria = new ConsultationRegisterCriteria(new \DateTimeImmutable(), page: 3, limit: 25);

        self::assertSame(50, $criteria->offset());
        self::assertFalse($criteria->isImpossible());
    }

    /**
     * @return iterable<string, array{0: ConsultationRegisterCriteria}>
     */
    public static function provideAnEmptyRestrictionCanMatchNothingCases(): iterable
    {
        $day = new \DateTimeImmutable();

        yield 'no visit kept' => [new ConsultationRegisterCriteria($day, restrictToAdmissionIds: [])];
        yield 'no act code' => [new ConsultationRegisterCriteria($day, restrictToActCodes: [])];
        yield 'no consultation' => [new ConsultationRegisterCriteria($day, consultationIds: [])];
    }

    #[DataProvider('provideAnEmptyRestrictionCanMatchNothingCases')]
    public function testAnEmptyRestrictionCanMatchNothing(ConsultationRegisterCriteria $criteria): void
    {
        self::assertTrue($criteria->isImpossible());
    }

    /**
     * @return iterable<string, array{0: int, 1: int, 2: ?string}>
     */
    public static function provideInvalidCriteriaAreRefusedCases(): iterable
    {
        yield 'page 0' => [0, 25, null];
        yield 'limit 0' => [1, 0, null];
        yield 'limit 101' => [1, 101, null];
        yield 'unknown stage' => [1, 25, 'ARCHIVED'];
    }

    #[DataProvider('provideInvalidCriteriaAreRefusedCases')]
    public function testInvalidCriteriaAreRefused(int $page, int $limit, ?string $stage): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ConsultationRegisterCriteria(new \DateTimeImmutable(), stage: $stage, page: $page, limit: $limit);
    }

    public function testStagesAndKindsAreListed(): void
    {
        self::assertSame(['IN_PROGRESS', 'TO_CLOSE', 'CLOSED'], ConsultationRegisterCriteria::stages());
        self::assertSame(['URGENT', 'SURGERY', 'LAB'], ConsultationRegisterCriteria::kinds());
    }

    public function testTheMotifIsTheFirstTagThenTheChiefComplaint(): void
    {
        self::assertSame('Vaccination', $this->item(['Vaccination', 'Bilan'], 'Rappel')->motif());
        self::assertSame('Rappel', $this->item([], 'Rappel')->motif());
        self::assertSame('Consultation', $this->item([], null)->motif());
    }

    public function testThePageCountNeverDropsBelowOne(): void
    {
        self::assertSame(1, new ConsultationRegisterPage([], 0, 1, 25, 0, 0, 0, 0)->pageCount());
        self::assertSame(3, new ConsultationRegisterPage([], 51, 1, 25, 0, 0, 0, 0)->pageCount());
    }

    /**
     * @param list<string> $motifs
     */
    private function item(array $motifs, ?string $chiefComplaint): ConsultationRegisterItem
    {
        return new ConsultationRegisterItem(
            consultationId: 'c',
            admissionId: 'a',
            patientId: 'p',
            practitionerUserId: 'v',
            status: 'OPEN',
            stage: 'IN_PROGRESS',
            startedAtUtc: '2026-05-01 09:00:00',
            closedAtUtc: null,
            durationMinutes: null,
            chiefComplaint: $chiefComplaint,
            summary: null,
            motifs: $motifs,
            primaryDiagnosis: null,
            weightKg: null,
            isUrgent: false,
            hasSurgery: false,
            labAnalyses: [],
            billedItems: [],
            totalHtMinorUnits: 0,
            totalTvaMinorUnits: 0,
            totalTtcMinorUnits: 0,
            currency: 'EUR',
        );
    }
}
