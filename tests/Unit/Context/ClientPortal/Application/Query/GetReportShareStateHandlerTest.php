<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Query;

use App\Context\ClientPortal\Application\Port\ConsultationDocumentsInterface;
use App\Context\ClientPortal\Application\Port\PublishedDocumentReadModelInterface;
use App\Context\ClientPortal\Application\Port\ReportShareability;
use App\Context\ClientPortal\Application\Query\GetReportShareState\GetReportShareState;
use App\Context\ClientPortal\Application\Query\GetReportShareState\GetReportShareStateHandler;
use App\Context\ClientPortal\Application\Query\GetReportShareState\ReportShareState;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GetReportShareStateHandlerTest extends TestCase
{
    private const string CLINIC       = '01960000-0000-7000-8000-00000000e001';
    private const string CONSULTATION = '01960000-0000-7000-8000-00000000e002';

    public function testAnUnknownConsultationIsNotShareable(): void
    {
        $state = $this->handler(null, null)(new GetReportShareState(self::CLINIC, self::CONSULTATION));

        self::assertSame([false, ReportShareState::UNKNOWN, null], [$state->shareable, $state->reason, $state->sharedAt]);
    }

    public function testAClosedConsultationWithAPrimaryOwnerIsShareableWithItsLastShare(): void
    {
        $sharedAt = new \DateTimeImmutable('2026-10-30 14:05', new \DateTimeZone('UTC'));
        $state    = $this->handler(self::shareability(null, 'animal', 'owner', '2026-10-30 12:00'), $sharedAt)(new GetReportShareState(self::CLINIC, self::CONSULTATION));

        self::assertSame([true, null, $sharedAt], [$state->shareable, $state->reason, $state->sharedAt]);
    }

    public function testANeverSharedReportHasNoShareDate(): void
    {
        $state = $this->handler(self::shareability(null, 'animal', 'owner', '2026-10-30 12:00'), null)(new GetReportShareState(self::CLINIC, self::CONSULTATION));

        self::assertSame([true, null, null], [$state->shareable, $state->reason, $state->sharedAt]);
    }

    /**
     * @return iterable<string, array{?string, ?string, ?string, ?string, string}>
     */
    public static function provideARefusedShareCarriesItsReasonCases(): iterable
    {
        yield 'open' => [ReportShareability::NOT_CLOSED, 'animal', 'owner', null, ReportShareState::NOT_CLOSED];
        yield 'unlinked patient' => [ReportShareability::NO_ANIMAL, null, null, '2026-10-30 12:00', ReportShareState::NO_ANIMAL];
        yield 'no primary owner' => [ReportShareability::NO_PRIMARY_OWNER, 'animal', null, '2026-10-30 12:00', ReportShareState::NO_PRIMARY_OWNER];
        yield 'no close time' => [null, 'animal', 'owner', null, ReportShareState::NOT_CLOSED];
        yield 'no animal, no cause' => [null, null, 'owner', '2026-10-30 12:00', ReportShareState::NOT_CLOSED];
        yield 'no owner, no cause' => [null, 'animal', null, '2026-10-30 12:00', ReportShareState::NOT_CLOSED];
    }

    #[DataProvider('provideARefusedShareCarriesItsReasonCases')]
    public function testARefusedShareCarriesItsReason(?string $refusal, ?string $animalId, ?string $ownerId, ?string $closedAt, string $reason): void
    {
        $state = $this->handler(self::shareability($refusal, $animalId, $ownerId, $closedAt), null)(new GetReportShareState(self::CLINIC, self::CONSULTATION));

        self::assertSame([false, $reason], [$state->shareable, $state->reason]);
    }

    private function handler(?ReportShareability $shareability, ?\DateTimeImmutable $sharedAt): GetReportShareStateHandler
    {
        $consultations = $this->createStub(ConsultationDocumentsInterface::class);
        $consultations->method('shareability')->willReturn($shareability);
        $documents = $this->createStub(PublishedDocumentReadModelInterface::class);
        $documents->method('reportSharedAt')->willReturn($sharedAt);

        return new GetReportShareStateHandler($consultations, $documents);
    }

    private static function shareability(?string $refusal, ?string $animalId, ?string $ownerId, ?string $closedAt): ReportShareability
    {
        $utc = new \DateTimeZone('UTC');

        return new ReportShareability(
            $refusal,
            'patient',
            $animalId,
            $ownerId,
            new \DateTimeImmutable('2026-10-30 11:00', $utc),
            null !== $closedAt ? new \DateTimeImmutable($closedAt, $utc) : null,
            'Dr Martin',
        );
    }
}
