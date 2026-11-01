<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Query\ListClientConsentHistory;

use App\Context\Client\Application\Port\ClientConsentReaderInterface;
use App\Context\Client\Application\Query\ListClientConsentHistory\ConsentHistoryItemView;
use App\Context\Client\Application\Query\ListClientConsentHistory\ListClientConsentHistory;
use App\Context\Client\Application\Query\ListClientConsentHistory\ListClientConsentHistoryHandler;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Shared\Application\Pagination\Cursor;
use App\Shared\Application\Pagination\InvalidCursorException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The history is paged newest first; the cursor carries the date and the id
 * of the last row and is checked before anything is read.
 */
final class ListClientConsentHistoryHandlerTest extends TestCase
{
    private const string CLINIC_ID = '0192a000-0000-7000-8000-0000000c09c1';
    private const string CLIENT_ID = '0192a000-0000-7000-8000-0000000c0901';

    public function testAFullPageGivesTheCursorOfItsLastRow(): void
    {
        $rows = [
            $this->row('0192a000-0000-7000-8000-0000000c0913', '2026-10-03 10:00:00'),
            $this->row('0192a000-0000-7000-8000-0000000c0912', '2026-10-02 10:00:00'),
            $this->row('0192a000-0000-7000-8000-0000000c0911', '2026-10-01 10:00:00'),
        ];

        $reader = $this->createMock(ClientConsentReaderInterface::class);
        $reader->expects(self::once())
            ->method('historyOf')
            ->with(self::callback(static fn (ClinicId $id): bool => self::CLINIC_ID === $id->toString()), [self::CLIENT_ID], null, 3)
            ->willReturn($rows)
        ;

        $page = (new ListClientConsentHistoryHandler($reader))(new ListClientConsentHistory(self::CLINIC_ID, [self::CLIENT_ID, self::CLIENT_ID], null, 2));

        self::assertSame([$rows[0], $rows[1]], $page->items);
        self::assertNotNull($page->nextCursor);
        self::assertSame(['at' => '2026-10-02 10:00:00', 'id' => '0192a000-0000-7000-8000-0000000c0912'], Cursor::decode($page->nextCursor)->position());
    }

    public function testTheCursorIsPassedOnAndTheLastPageHasNone(): void
    {
        $cursor = Cursor::fromPosition(['at' => '2026-10-02 10:00:00', 'id' => '0192a000-0000-7000-8000-0000000c0912'])->encode();
        $row    = $this->row('0192a000-0000-7000-8000-0000000c0911', '2026-10-01 10:00:00');

        $reader = $this->createMock(ClientConsentReaderInterface::class);
        $reader->expects(self::once())
            ->method('historyOf')
            ->with(
                self::anything(),
                [self::CLIENT_ID],
                self::callback(static fn (mixed $after): bool => \is_array($after)
                    && $after['at'] instanceof \DateTimeImmutable
                    && '2026-10-02 10:00:00' === $after['at']->format('Y-m-d H:i:s')
                    && 'UTC' === $after['at']->getTimezone()->getName()
                    && '0192a000-0000-7000-8000-0000000c0912' === $after['id']),
                Cursor::DEFAULT_LIMIT + 1,
            )
            ->willReturn([$row])
        ;

        $page = (new ListClientConsentHistoryHandler($reader))(new ListClientConsentHistory(self::CLINIC_ID, [self::CLIENT_ID], $cursor));

        self::assertSame([$row], $page->items);
        self::assertNull($page->nextCursor);
    }

    public function testNoClientReadsNothing(): void
    {
        $reader = $this->createMock(ClientConsentReaderInterface::class);
        $reader->expects(self::never())->method('historyOf');

        $page = (new ListClientConsentHistoryHandler($reader))(new ListClientConsentHistory(self::CLINIC_ID, []));

        self::assertSame([], $page->items);
        self::assertNull($page->nextCursor);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideAForeignCursorIsRefusedCases(): iterable
    {
        yield 'not a cursor' => ['%%%'];
        yield 'no date' => [Cursor::fromPosition(['id' => '0192a000-0000-7000-8000-0000000c0912'])->encode()];
        yield 'an id that is no UUID' => [Cursor::fromPosition(['at' => '2026-10-02 10:00:00', 'id' => 'x'])->encode()];
        yield 'an integer date' => [Cursor::fromPosition(['at' => 20261002, 'id' => '0192a000-0000-7000-8000-0000000c0912'])->encode()];
        yield 'an impossible date' => [Cursor::fromPosition(['at' => '2026-13-45 10:00:00', 'id' => '0192a000-0000-7000-8000-0000000c0912'])->encode()];
        yield 'another date format' => [Cursor::fromPosition(['at' => '2026-10-02T10:00:00', 'id' => '0192a000-0000-7000-8000-0000000c0912'])->encode()];
    }

    #[DataProvider('provideAForeignCursorIsRefusedCases')]
    public function testAForeignCursorIsRefused(string $cursor): void
    {
        $reader = $this->createMock(ClientConsentReaderInterface::class);
        $reader->expects(self::never())->method('historyOf');

        $this->expectException(InvalidCursorException::class);

        (new ListClientConsentHistoryHandler($reader))(new ListClientConsentHistory(self::CLINIC_ID, [self::CLIENT_ID], $cursor));
    }

    private function row(string $id, string $at): ConsentHistoryItemView
    {
        return new ConsentHistoryItemView(
            id: $id,
            clientId: self::CLIENT_ID,
            kind: 'marketing',
            channel: 'email',
            action: 'withdrawn',
            at: new \DateTimeImmutable($at, new \DateTimeZone('UTC')),
            source: 'portal',
            actorType: 'portal_user',
            actorId: null,
            authorLabel: 'Hélène Lambert',
            wordingVersion: null,
            approximate: false,
        );
    }
}
