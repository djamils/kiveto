<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Query\Pets;

use App\Context\ClientPortal\Application\Port\VisitSummary;
use App\Context\ClientPortal\Application\Query\ListPetTimeline\ListPetTimeline;
use App\Context\ClientPortal\Application\Query\ListPetTimeline\ListPetTimelineHandler;
use App\Context\ClientPortal\Application\Query\ListPetTimeline\TimelineDocument;
use App\Context\ClientPortal\Application\Query\ListPetTimeline\TimelineEntry;
use App\Context\ClientPortal\Application\Service\DocumentCursor;
use App\Context\ClientPortal\Domain\Exception\PetNotFoundException;
use App\Shared\Application\Pagination\Cursor;
use App\Shared\Application\Pagination\InvalidCursorException;
use App\Tests\Unit\Context\ClientPortal\Support\PetWorld as W;
use PHPUnit\Framework\TestCase;

final class ListPetTimelineHandlerTest extends TestCase
{
    private const string R1      = '01960000-0000-7000-8000-0000000f0001';
    private const string R2      = '01960000-0000-7000-8000-0000000f0002';
    private const string R3      = '01960000-0000-7000-8000-0000000f0003';
    private const string INVOICE = '01960000-0000-7000-8000-0000000f0009';
    private const string V1      = '01960000-0000-7000-8000-0000000e0001';
    private const string V2      = '01960000-0000-7000-8000-0000000e0002';
    private const string V3      = '01960000-0000-7000-8000-0000000e0003';

    public function testAPageListsTheSharedVisitsWithTheirStartTitleVetAndDocuments(): void
    {
        $world = new W('America/Martinique');
        $world->pet();
        $world->model->rows = [
            W::report(self::R1, self::V1, '2026-10-20 10:00:00'),
            W::report(self::R2, self::V2, '2026-10-10 10:00:00', null),
        ];
        $world->model->visitRows = [
            W::report(self::INVOICE, self::V1, '2026-10-20 11:00:00', null, 'invoice'),
            W::report(self::R1, self::V1, '2026-10-20 10:00:00'),
            W::report(self::R2, self::V2, '2026-10-10 10:00:00', null),
        ];
        $world->fakes->visits = [self::V1 => new VisitSummary(self::V1, new \DateTimeImmutable('2026-10-20 02:30:00', new \DateTimeZone('UTC')), 'Vaccination et bilan annuel')];

        $page = $this->handler($world)(new ListPetTimeline(W::USER, W::CLINIC, W::PET));

        self::assertNull($page->nextCursor);
        self::assertSame([[W::USER, W::CLINIC, 'report', W::PET, null, 21]], $world->model->listings);
        self::assertSame([
            [self::V1, '2026-10-19T22:30:00-04:00', 'Dr Claire Lambert', 'Vaccination et bilan annuel', [[self::INVOICE, 'invoice'], [self::R1, 'report']]],
            // Consultation no longer answers for V2: the registry's date and title stand in.
            [self::V2, '2026-10-10T06:00:00-04:00', null, 'Compte rendu ' . self::R2, [[self::R2, 'report']]],
        ], array_map(static fn (TimelineEntry $e): array => [
            $e->visitId,
            $e->date->format('c'),
            $e->vet,
            $e->title,
            array_map(static fn (TimelineDocument $d): array => [$d->id, $d->type], $e->documents),
        ], $page->items));
        self::assertContains('summariesOf:' . W::CLINIC . ':' . self::V1 . ',' . self::V2, $world->fakes->calls);
        self::assertSame(['listableOfVisits:' . W::USER . ':' . W::CLINIC . ':' . self::V1 . ',' . self::V2], $world->model->calls);
    }

    public function testAFullPageHasANextCursorThatResumesAfterItsLastVisit(): void
    {
        $world = new W();
        $world->pet();
        $world->model->rows = [
            W::report(self::R1, self::V1, '2026-10-20 10:00:00'),
            W::report(self::R2, self::V2, '2026-10-10 10:00:00'),
            W::report(self::R3, self::V3, '2026-10-01 10:00:00'),
        ];

        $page = $this->handler($world)(new ListPetTimeline(W::USER, W::CLINIC, W::PET, null, 2));

        self::assertCount(2, $page->items);
        self::assertNotNull($page->nextCursor);
        $position = DocumentCursor::position($page->nextCursor);
        self::assertSame(['2026-10-10 10:00:00', self::R2], [$position->issuedAt->format('Y-m-d H:i:s'), $position->id]);

        $this->handler($world)(new ListPetTimeline(W::USER, W::CLINIC, W::PET, $page->nextCursor, 2));
        $after = $world->model->listings[1][4] ?? null;
        self::assertNotNull($after);
        self::assertSame([self::R2, 3], [$after->id, $world->model->listings[1][5]]);
    }

    public function testAnInvalidCursorIsRefused(): void
    {
        $world = new W();
        $world->pet();

        $this->expectException(InvalidCursorException::class);
        $this->handler($world)(new ListPetTimeline(W::USER, W::CLINIC, W::PET, Cursor::fromPosition(['at' => '2026-10-10', 'id' => self::R1])->encode()));
    }

    public function testAPetNotVisibleToTheAccountIsNotFound(): void
    {
        $world = new W();
        $world->pet(W::PET2, 'Rex', 'alive', W::CLIENT2);

        $this->expectException(PetNotFoundException::class);
        $this->handler($world)(new ListPetTimeline(W::USER, W::CLINIC, W::PET2));
    }

    public function testAPetWithoutSharedVisitHasAnEmptyTimeline(): void
    {
        $world = new W();
        $world->pet(W::PET, 'Moka', 'deceased');

        $page = $this->handler($world)(new ListPetTimeline(W::USER, W::CLINIC, W::PET));

        self::assertSame([], $page->items);
        self::assertNull($page->nextCursor);
    }

    private function handler(W $world): ListPetTimelineHandler
    {
        return new ListPetTimelineHandler($world->ownerPets(), $world->model, $world->fakes, $world->documents->fakes);
    }
}
