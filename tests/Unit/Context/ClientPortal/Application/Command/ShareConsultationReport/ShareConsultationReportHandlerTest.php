<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Command\ShareConsultationReport;

use App\Context\ClientPortal\Application\Command\ShareConsultationReport\ShareConsultationReport;
use App\Context\ClientPortal\Application\Command\ShareConsultationReport\ShareConsultationReportHandler;
use App\Context\ClientPortal\Application\Port\ClinicLabel;
use App\Context\ClientPortal\Application\Port\ReportShareability;
use App\Context\ClientPortal\Domain\Document\DocumentType;
use App\Context\ClientPortal\Domain\Document\PublicationStatus;
use App\Context\ClientPortal\Domain\Document\PublishedDocument;
use App\Context\ClientPortal\Domain\Exception\ReportNotShareableException;
use App\Tests\Unit\Context\ClientPortal\Support\DocumentWorld as W;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ShareConsultationReportHandlerTest extends TestCase
{
    private const string CONSULT = W::SOURCE;
    private const string ANIMAL2 = '01960000-0000-7000-8000-0000000f3002';

    public function testAnUnknownConsultationIsRefused(): void
    {
        $this->assertRefused(new W(), ReportNotShareableException::UNKNOWN);
    }

    /**
     * @return iterable<string, array{?string, ?string, ?string, bool, string}>
     */
    public static function provideAReportThatCannotBeSharedIsRefusedWithItsReasonCases(): iterable
    {
        yield 'not closed' => [ReportShareability::NOT_CLOSED, W::ANIMAL, W::CLIENT, false, 'not_closed'];
        yield 'no animal' => [ReportShareability::NO_ANIMAL, null, W::CLIENT, true, 'no_animal'];
        yield 'no primary owner' => [ReportShareability::NO_PRIMARY_OWNER, W::ANIMAL, null, true, 'no_primary_owner'];
        yield 'not closed without reason' => [null, W::ANIMAL, W::CLIENT, false, 'not_closed'];
        yield 'no animal without reason' => [null, null, W::CLIENT, true, 'not_closed'];
        yield 'no owner without reason' => [null, W::ANIMAL, null, true, 'not_closed'];
    }

    #[DataProvider('provideAReportThatCannotBeSharedIsRefusedWithItsReasonCases')]
    public function testAReportThatCannotBeSharedIsRefusedWithItsReason(
        ?string $refusal,
        ?string $animalId,
        ?string $ownerId,
        bool $closed,
        string $reason,
    ): void {
        $world = new W();
        $this->consultation($world, $refusal, $animalId, $ownerId, $closed);

        $this->assertRefused($world, $reason);
    }

    public function testTheFirstShareToALinkedOwnerPublishesVersionOne(): void
    {
        $world = new W();
        $world->link();
        $this->consultation($world);
        $world->fakes->clinics[W::CLINIC] = new ClinicLabel('Clinique', 'Nouméa', 'Pacific/Noumea');

        $result = $this->handler($world)(new ShareConsultationReport(W::CLINIC, self::CONSULT, 'actor'));

        $document = $world->documents->only();
        self::assertSame([$document->id()->toString(), true], [$result->documentId, $result->linked]);
        self::assertSame(
            [
                W::CLIENT, DocumentType::REPORT, self::CONSULT, self::CONSULT, W::PATIENT, [W::ANIMAL],
                'Compte rendu du 21/10/2026', '2026-10-20 16:00', 'Dr Durand', PublicationStatus::PENDING_FILE, 1,
            ],
            [
                $document->clientId(), $document->type(), $document->sourceRef(), $document->consultationId(),
                $document->patientId(), $document->petIds(), $document->title(),
                $document->issuedAt()->format('Y-m-d H:i'), $document->practitionerName(), $document->status(),
                $document->pendingFileVersion(),
            ],
            'started on 20/10 at 23:30 UTC, i.e. the 21st in Nouméa; issued when closed',
        );
        self::assertSame(['store:' . $result->documentId . ':1'], $world->sources->jobs);
        self::assertSame(['client_portal.document.published.v1'], $world->fakes->eventNames());
    }

    public function testTheFirstShareToAnOwnerWithoutPortalWaitsForTheirLink(): void
    {
        $world = new W();
        $this->consultation($world);

        $result = $this->handler($world)(new ShareConsultationReport(W::CLINIC, self::CONSULT, 'actor'));

        self::assertFalse($result->linked);
        self::assertSame('Compte rendu du 20/10/2026', $world->documents->only()->title(), 'UTC by default');
        self::assertSame(PublicationStatus::AWAITING_LINK, $world->documents->only()->status());
        self::assertSame([], $world->sources->jobs);
    }

    public function testAReShareRequestsTheNextVersionForTheCurrentOwnerAndAnimal(): void
    {
        $world = new W();
        $world->link(W::CLIENT2);
        $world->document(DocumentType::REPORT, PublicationStatus::AVAILABLE, 2, petIds: ['old-animal']);
        $this->consultation($world, ownerId: W::CLIENT2, animalId: self::ANIMAL2, practitioner: 'Dr Lemoine');
        $world->clock->advance('+1 day');

        $result = $this->handler($world)(new ShareConsultationReport(W::CLINIC, self::CONSULT, 'actor'));

        $document = $world->documents->only();
        self::assertSame([W::DOC, true], [$result->documentId, $result->linked]);
        self::assertSame(
            [W::CLIENT2, 'Dr Lemoine', [self::ANIMAL2], PublicationStatus::PENDING_FILE, 2, 3, '2026-10-30'],
            [
                $document->clientId(), $document->practitionerName(), $document->petIds(), $document->status(),
                $document->fileVersion(), $document->pendingFileVersion(), $document->updatedAt()->format('Y-m-d'),
            ],
        );
        self::assertSame(1, $world->documents->saves);
        self::assertSame(['store:' . W::DOC . ':3'], $world->sources->jobs);
        self::assertSame([], $world->fakes->events);
    }

    public function testAReShareToAnUnlinkedOwnerWaitsAgain(): void
    {
        $world = new W();
        $world->document(DocumentType::REPORT, PublicationStatus::AVAILABLE, 1);
        $this->consultation($world);

        $result = $this->handler($world)(new ShareConsultationReport(W::CLINIC, self::CONSULT, 'actor'));

        self::assertFalse($result->linked);
        self::assertSame(PublicationStatus::AWAITING_LINK, $world->documents->only()->status());
        self::assertSame([], $world->sources->jobs);
    }

    public function testAConcurrentFirstShareIsSharedAgainOnTheWinningRow(): void
    {
        $world = new W();
        $world->link();
        $this->consultation($world);
        $world->documents->beforeInsert = static function (PublishedDocument $loser) use ($world): void {
            $world->documents->beforeInsert = null;
            $world->document(DocumentType::REPORT, PublicationStatus::PENDING_FILE, 0, 1);
        };

        $result = $this->handler($world)(new ShareConsultationReport(W::CLINIC, self::CONSULT, 'actor'));

        $document = $world->documents->only();
        self::assertSame([W::DOC, true], [$result->documentId, $result->linked]);
        self::assertSame(
            [0, 2],
            [$document->fileVersion(), $document->pendingFileVersion()],
            'the losing click asks for the version after the pending one',
        );
        self::assertSame(['store:' . W::DOC . ':2'], $world->sources->jobs);
    }

    public function testAWinningRowThatVanishedIsALogicError(): void
    {
        $world = new W();
        $this->consultation($world);
        $world->documents->loseInserts = true;

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The shared report row vanished.');

        $this->handler($world)(new ShareConsultationReport(W::CLINIC, self::CONSULT, 'actor'));
    }

    private function assertRefused(W $world, string $reason): void
    {
        try {
            $this->handler($world)(new ShareConsultationReport(W::CLINIC, self::CONSULT, 'actor'));
            self::fail('Expected a refusal.');
        } catch (ReportNotShareableException $refusal) {
            self::assertSame($reason, $refusal->reason);
            self::assertStringContainsString('(' . $reason . ')', $refusal->getMessage());
        }

        self::assertSame([], $world->documents->documents);
    }

    private function consultation(
        W $world,
        ?string $refusal = null,
        ?string $animalId = W::ANIMAL,
        ?string $ownerId = W::CLIENT,
        bool $closed = true,
        string $practitioner = 'Dr Durand',
    ): void {
        $utc = new \DateTimeZone('UTC');

        $world->sources->shareabilities[self::CONSULT] = new ReportShareability(
            $refusal,
            W::PATIENT,
            $animalId,
            $ownerId,
            new \DateTimeImmutable('2026-10-20 23:30', $utc),
            $closed ? new \DateTimeImmutable('2026-10-20 16:00', $utc) : null,
            $practitioner,
        );
    }

    private function handler(W $world): ShareConsultationReportHandler
    {
        return new ShareConsultationReportHandler(
            $world->sources,
            $world->documents,
            $world->fakes,
            $world->publisher(),
            $world->fakes,
            $world->clock,
        );
    }
}
