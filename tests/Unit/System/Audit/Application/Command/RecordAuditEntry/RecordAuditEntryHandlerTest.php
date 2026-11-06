<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Audit\Application\Command\RecordAuditEntry;

use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\System\Audit\Application\Command\RecordAuditEntry\RecordAuditEntry;
use App\System\Audit\Application\Command\RecordAuditEntry\RecordAuditEntryHandler;
use App\System\Audit\Domain\AuditEntry;
use App\System\Audit\Domain\Repository\AuditEntryRepositoryInterface;
use App\System\Audit\Domain\ValueObject\ActorKind;
use App\System\Audit\Domain\ValueObject\AuditCategory;
use App\Tests\Shared\Time\FrozenClock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RecordAuditEntryHandlerTest extends TestCase
{
    private const string ID     = '01960000-0000-7000-8000-000000000e01';
    private const string ACTOR  = '01960000-0000-7000-8000-000000000a01';
    private const string CLINIC = '01960000-0000-7000-8000-000000000c01';
    private const string USER   = '01960000-0000-7000-8000-000000000b01';

    private ?AuditEntry $appended = null;

    public function testAppendsTheEntryAtTheClocksTimeAndAnswersItsId(): void
    {
        $now = new \DateTimeImmutable('2026-11-01T02:14:00Z');

        $id = $this->handler($now)(new RecordAuditEntry(
            actorKind: ActorKind::KIVETO,
            actorId: self::ACTOR,
            actorName: 'Djamil Soidiki',
            action: 'support.session_started',
            category: AuditCategory::SUPPORT,
            summary: 'Session support démarrée',
            clinicId: self::CLINIC,
            clinicName: 'Clinique du Prado',
            targetUserId: self::USER,
            ipAddress: '91.12.34.12',
            details: [['label' => 'Mode', 'value' => 'Lecture seule'], ['label' => 'Durée', 'value' => '30 min']],
        ));

        self::assertSame(self::ID, $id);
        $entry = $this->appended();
        self::assertSame(self::ID, $entry->id()->toString());
        self::assertSame($now, $entry->occurredAt());
        self::assertSame(ActorKind::KIVETO, $entry->actor()->kind());
        self::assertSame(self::ACTOR, $entry->actor()->id());
        self::assertSame('Djamil Soidiki', $entry->actor()->name());
        self::assertSame('support.session_started', $entry->action()->toString());
        self::assertSame(AuditCategory::SUPPORT, $entry->category());
        self::assertSame('Session support démarrée', $entry->summary());
        self::assertSame(self::CLINIC, $entry->clinicId());
        self::assertSame('Clinique du Prado', $entry->clinicName());
        self::assertSame(self::USER, $entry->targetUserId());
        self::assertSame('91.12.34.12', $entry->ipAddress());
        self::assertCount(2, $entry->details());
        self::assertSame('Mode', $entry->details()[0]->label());
        self::assertSame('Lecture seule', $entry->details()[0]->value());
        self::assertSame('30 min', $entry->details()[1]->value());
    }

    public function testAGivenDateWinsOverTheClock(): void
    {
        $at = new \DateTimeImmutable('2026-10-27T07:13:00Z');

        $this->handler(new \DateTimeImmutable('2026-11-06T10:00:00Z'))(new RecordAuditEntry(
            actorKind: ActorKind::SYSTEM,
            actorId: null,
            actorName: 'Import ANMV',
            action: 'registry.import_succeeded',
            category: AuditCategory::REGISTRY,
            summary: 'Registre mis à jour',
            occurredAt: $at,
        ));

        self::assertSame($at, $this->appended()->occurredAt());
        self::assertSame(ActorKind::SYSTEM, $this->appended()->actor()->kind());
        self::assertSame('Import ANMV', $this->appended()->actor()->name());
    }

    /**
     * @return iterable<string, array{0: ActorKind, 1: ?string, 2: ?string}>
     */
    public static function provideEachKindOfActorIsRecordedCases(): iterable
    {
        yield 'staff' => [ActorKind::STAFF, self::ACTOR, 'Dr Claire Martin'];
        yield 'unknown' => [ActorKind::UNKNOWN, null, null];
    }

    #[DataProvider('provideEachKindOfActorIsRecordedCases')]
    public function testEachKindOfActorIsRecorded(ActorKind $kind, ?string $id, ?string $name): void
    {
        $this->handler(new \DateTimeImmutable('2026-10-26T23:41:00Z'))(new RecordAuditEntry(
            actorKind: $kind,
            actorId: $id,
            actorName: $name,
            action: 'auth.login_failed',
            category: AuditCategory::ACCESS,
            summary: 'Échec de connexion',
        ));

        self::assertSame($kind, $this->appended()->actor()->kind());
        self::assertSame($id, $this->appended()->actor()->id());
        self::assertSame($name, $this->appended()->actor()->name());
    }

    private function appended(): AuditEntry
    {
        $entry = $this->appended;
        \assert($entry instanceof AuditEntry, 'the handler appended an entry');

        return $entry;
    }

    private function handler(\DateTimeImmutable $now): RecordAuditEntryHandler
    {
        $entries = $this->createMock(AuditEntryRepositoryInterface::class);
        $entries->expects(self::once())->method('append')->willReturnCallback(function (AuditEntry $entry): void {
            $this->appended = $entry;
        });

        $uuid = $this->createStub(UuidGeneratorInterface::class);
        $uuid->method('generate')->willReturn(self::ID);

        return new RecordAuditEntryHandler($entries, $uuid, new FrozenClock($now));
    }
}
