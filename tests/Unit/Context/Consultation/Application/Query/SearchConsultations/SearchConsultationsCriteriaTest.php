<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Application\Query\SearchConsultations;

use App\Context\Consultation\Application\Query\SearchConsultations\SearchConsultationsCriteria;
use PHPUnit\Framework\TestCase;

final class SearchConsultationsCriteriaTest extends TestCase
{
    private const string PATIENT_ID = '11111111-1111-4111-8111-111111111111';

    public function testDefaultsAreAccepted(): void
    {
        $criteria = new SearchConsultationsCriteria();

        self::assertNull($criteria->searchTerm);
        self::assertSame([], $criteria->textMatchPatientIds);
        self::assertNull($criteria->restrictToPatientIds);
        self::assertNull($criteria->startedAfterUtc);
        self::assertSame([], $criteria->statuses);
        self::assertSame([], $criteria->practitionerUserIds);
        self::assertSame([], $criteria->practitionerOrder);
        self::assertSame('datetime', $criteria->sort);
        self::assertSame('desc', $criteria->direction);
        self::assertSame(1, $criteria->page);
        self::assertSame(25, $criteria->limit);
    }

    public function testEveryArgumentIsKept(): void
    {
        $startedAfterUtc = new \DateTimeImmutable('2026-04-10 09:00:00');

        $criteria = new SearchConsultationsCriteria(
            searchTerm: 'Rex',
            textMatchPatientIds: [self::PATIENT_ID],
            restrictToPatientIds: [self::PATIENT_ID],
            startedAfterUtc: $startedAfterUtc,
            statuses: ['OPEN'],
            practitionerUserIds: ['user-1'],
            practitionerOrder: ['user-1', 'user-2'],
            sort: SearchConsultationsCriteria::SORT_AMOUNT,
            direction: 'asc',
            page: 2,
            limit: 50,
        );

        self::assertSame('Rex', $criteria->searchTerm);
        self::assertSame([self::PATIENT_ID], $criteria->textMatchPatientIds);
        self::assertSame([self::PATIENT_ID], $criteria->restrictToPatientIds);
        self::assertSame($startedAfterUtc, $criteria->startedAfterUtc);
        self::assertSame(['OPEN'], $criteria->statuses);
        self::assertSame(['user-1'], $criteria->practitionerUserIds);
        self::assertSame(['user-1', 'user-2'], $criteria->practitionerOrder);
        self::assertSame('amount', $criteria->sort);
        self::assertSame('asc', $criteria->direction);
        self::assertSame(2, $criteria->page);
        self::assertSame(50, $criteria->limit);
    }

    public function testOffsetIsZeroOnTheFirstPage(): void
    {
        self::assertSame(0, (new SearchConsultationsCriteria(page: 1, limit: 25))->offset());
    }

    public function testOffsetSkipsThePreviousPages(): void
    {
        self::assertSame(50, (new SearchConsultationsCriteria(page: 3, limit: 25))->offset());
        self::assertSame(20, (new SearchConsultationsCriteria(page: 3, limit: 10))->offset());
    }

    public function testAnEmptyPatientRestrictionCanNeverMatch(): void
    {
        self::assertTrue((new SearchConsultationsCriteria(restrictToPatientIds: []))->isImpossible());
    }

    public function testNoPatientRestrictionIsNotImpossible(): void
    {
        self::assertFalse((new SearchConsultationsCriteria(restrictToPatientIds: null))->isImpossible());
    }

    public function testAFilledPatientRestrictionIsNotImpossible(): void
    {
        self::assertFalse(
            (new SearchConsultationsCriteria(restrictToPatientIds: [self::PATIENT_ID]))->isImpossible(),
        );
    }

    public function testSortableColumns(): void
    {
        self::assertSame(
            ['datetime', 'status', 'amount', 'vet'],
            SearchConsultationsCriteria::sortableColumns(),
        );
    }

    public function testEverySortableColumnIsAccepted(): void
    {
        foreach (SearchConsultationsCriteria::sortableColumns() as $column) {
            self::assertSame($column, (new SearchConsultationsCriteria(sort: $column))->sort);
        }
    }

    public function testBothDirectionsAreAccepted(): void
    {
        self::assertSame('asc', (new SearchConsultationsCriteria(direction: 'asc'))->direction);
        self::assertSame('desc', (new SearchConsultationsCriteria(direction: 'desc'))->direction);
    }

    public function testBothLimitBoundsAreAccepted(): void
    {
        self::assertSame(1, (new SearchConsultationsCriteria(limit: 1))->limit);
        self::assertSame(100, (new SearchConsultationsCriteria(limit: 100))->limit);
    }

    public function testAPageBelowOneIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Page must be >= 1.');

        new SearchConsultationsCriteria(page: 0);
    }

    public function testALimitBelowOneIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Limit must be between 1 and 100.');

        new SearchConsultationsCriteria(limit: 0);
    }

    public function testALimitAboveOneHundredIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Limit must be between 1 and 100.');

        new SearchConsultationsCriteria(limit: 101);
    }

    public function testAnUnknownSortColumnIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported sort column "created".');

        new SearchConsultationsCriteria(sort: 'created');
    }

    public function testAnUnknownSortDirectionIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported sort direction "sideways".');

        new SearchConsultationsCriteria(direction: 'sideways');
    }
}
