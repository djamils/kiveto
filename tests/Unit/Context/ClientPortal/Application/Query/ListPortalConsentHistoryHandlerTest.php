<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Query;

use App\Context\ClientPortal\Application\Port\ClientConsentChange;
use App\Context\ClientPortal\Application\Port\ClientConsentRecord;
use App\Context\ClientPortal\Application\Query\ListPortalConsentHistory\ListPortalConsentHistory;
use App\Context\ClientPortal\Application\Query\ListPortalConsentHistory\ListPortalConsentHistoryHandler;
use App\Context\ClientPortal\Application\Query\ListPortalConsentHistory\PortalConsentHistoryItem;
use App\Context\ClientPortal\Application\Service\PortalConsentFiles;
use App\Context\ClientPortal\Domain\Link\ClientLink;
use App\Context\ClientPortal\Domain\Link\ClientLinkId;
use App\Shared\Application\Pagination\Cursor;
use App\Shared\Application\Pagination\CursorPage;
use App\Shared\Application\Pagination\InvalidCursorException;
use App\Tests\Unit\Context\ClientPortal\Support\InMemoryClientConsents;
use App\Tests\Unit\Context\ClientPortal\Support\InMemoryClientLinks;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/** Task 33: one line per change, whatever the number of files it touched (AC 55, AC 92). */
final class ListPortalConsentHistoryHandlerTest extends TestCase
{
    private const string USER   = 'user-1';
    private const string CLINIC = 'clinic-1';

    private InMemoryClientLinks $links;

    private InMemoryClientConsents $consents;

    private int $sequence = 0;

    protected function setUp(): void
    {
        $this->links    = new InMemoryClientLinks();
        $this->consents = new InMemoryClientConsents();

        foreach (['client-1' => false, 'client-2' => false, 'client-archived' => true] as $clientId => $archived) {
            $this->links->save(ClientLink::confirm(ClientLinkId::fromString(Uuid::v7()->toRfc4122()), self::USER, self::CLINIC, $clientId, new \DateTimeImmutable('2026-10-01')));
            $this->consents->records[$clientId] = new ClientConsentRecord($clientId, null, $archived, []);
        }
    }

    public function testAChangeOnTwoFilesIsOneLineAndTheClinicIsNotNamed(): void
    {
        $this->row('client-1', 'billing', 'email', 'granted', '2026-01-10 08:00:00', 'system', 'system', 'Reprise', approximate: false);
        $this->row('client-2', 'billing', 'email', 'granted', '2026-01-10 08:00:00', 'system', 'system', 'Reprise', approximate: true);
        $this->row('client-1', 'appointment_reminders', 'sms', 'granted', '2026-10-01 09:00:00', 'clinic', 'staff', 'Claire Lambert');
        $this->row('client-1', 'marketing', 'email', 'withdrawn', '2026-10-20 10:00:00', 'portal', 'portal_user', 'Sophie Martin');
        $this->row('client-2', 'marketing', 'email', 'withdrawn', '2026-10-20 10:00:00', 'portal', 'portal_user', 'Sophie Martin');
        $this->row('client-archived', 'marketing', 'sms', 'granted', '2026-10-21 10:00:00', 'portal', 'portal_user', 'Sophie Martin');

        $page = $this->list();

        self::assertNull($page->nextCursor);
        self::assertSame([
            ['marketing', 'email', 'withdrawn', '2026-10-20 10:00:00', 'portal', 'Sophie Martin', false],
            ['appointment_reminders', 'sms', 'granted', '2026-10-01 09:00:00', 'clinic', 'La clinique', false],
            ['billing', 'email', 'granted', '2026-01-10 08:00:00', 'system', 'Reprise', true],
        ], self::lines($page));
        self::assertSame(['client-1', 'client-2'], $this->consents->historyCalls[0][0]);
    }

    public function testAPageBoundaryInsideAnInstantNeverSplitsALine(): void
    {
        foreach (['client-1', 'client-2'] as $clientId) {
            foreach (['marketing', 'billing', 'appointment_reminders'] as $kind) {
                $this->row($clientId, $kind, 'sms', 'granted', '2026-10-20 10:00:00', 'portal', 'portal_user', 'Sophie Martin');
            }
        }
        $this->row('client-1', 'marketing', 'email', 'granted', '2026-10-19 10:00:00', 'portal', 'portal_user', 'Sophie Martin');

        $kinds  = [];
        $cursor = null;

        do {
            $page = $this->list($cursor, 1);
            self::assertCount(1, $page->items);
            $kinds[] = self::lines($page)[0][0] . '/' . self::lines($page)[0][1];
            $cursor  = $page->nextCursor;
        } while (null !== $cursor);

        self::assertSame(['appointment_reminders/sms', 'billing/sms', 'marketing/sms', 'marketing/email'], $kinds);
    }

    public function testAPageReadsAsManyBatchesAsItNeeds(): void
    {
        $start = new \DateTimeImmutable('2026-10-01 00:00:00', new \DateTimeZone('UTC'));

        for ($i = 0; $i < 40; ++$i) {
            $at = $start->modify(\sprintf('+%d minutes', $i))->format('Y-m-d H:i:s');
            $this->row('client-1', 'marketing', 'sms', 0 === $i % 2 ? 'granted' : 'withdrawn', $at, 'portal', 'portal_user', 'Sophie Martin');
            $this->row('client-2', 'marketing', 'sms', 0 === $i % 2 ? 'granted' : 'withdrawn', $at, 'portal', 'portal_user', 'Sophie Martin');
        }

        $first = $this->list(null, 30);
        self::assertCount(30, $first->items);
        self::assertNotNull($first->nextCursor);
        self::assertCount(2, $this->consents->historyCalls);
        self::assertSame(50, $this->consents->historyCalls[0][2]);

        $second = $this->list($first->nextCursor, 30);
        self::assertNull($second->nextCursor);
        self::assertSame(
            array_map(static fn (int $i): string => $start->modify(\sprintf('+%d minutes', 39 - $i))->format('Y-m-d H:i:s'), range(0, 39)),
            array_column([...self::lines($first), ...self::lines($second)], 3),
        );
    }

    public function testNoHistoryIsAnEmptyPage(): void
    {
        $page = $this->list();

        self::assertSame([[], null], [$page->items, $page->nextCursor]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideACursorOfAnotherShapeIsRefusedCases(): iterable
    {
        yield 'not a cursor' => ['%%%'];
        yield 'no id' => [Cursor::fromPosition(['at' => '2026-10-20 10:00:00'])->encode()];
        yield 'id not a uuid' => [Cursor::fromPosition(['at' => '2026-10-20 10:00:00', 'id' => 'h-1'])->encode()];
        yield 'bad date' => [Cursor::fromPosition(['at' => '2026-13-45 10:00:00', 'id' => '00000000-0000-7000-8000-000000000001'])->encode()];
        yield 'integer date' => [Cursor::fromPosition(['at' => 20261020, 'id' => '00000000-0000-7000-8000-000000000001'])->encode()];
    }

    #[DataProvider('provideACursorOfAnotherShapeIsRefusedCases')]
    public function testACursorOfAnotherShapeIsRefused(string $cursor): void
    {
        $this->expectException(InvalidCursorException::class);

        $this->list($cursor);
    }

    /**
     * @return CursorPage<PortalConsentHistoryItem>
     */
    private function list(?string $cursor = null, ?int $limit = null): CursorPage
    {
        $handler = new ListPortalConsentHistoryHandler(new PortalConsentFiles($this->links, $this->consents), $this->consents);

        return $handler(new ListPortalConsentHistory(self::USER, self::CLINIC, $cursor, $limit));
    }

    private function row(string $clientId, string $kind, string $channel, string $action, string $at, string $source, string $actorType, string $author, bool $approximate = false): void
    {
        $this->consents->history[] = new ClientConsentChange(
            \sprintf('00000000-0000-7000-8000-%012d', ++$this->sequence),
            $clientId,
            $kind,
            $channel,
            $action,
            new \DateTimeImmutable($at, new \DateTimeZone('UTC')),
            $source,
            $actorType,
            $author,
            $approximate,
        );
    }

    /**
     * @param CursorPage<PortalConsentHistoryItem> $page
     *
     * @return list<array{string, string, string, string, string, string, bool}>
     */
    private static function lines(CursorPage $page): array
    {
        return array_map(static fn (PortalConsentHistoryItem $i): array => [$i->kind, $i->channel, $i->action, $i->at->format('Y-m-d H:i:s'), $i->source, $i->author, $i->approximate], $page->items);
    }
}
