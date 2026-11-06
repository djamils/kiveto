<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Audit\Domain;

use App\System\Audit\Domain\AuditEntry;
use App\System\Audit\Domain\ValueObject\AuditAction;
use App\System\Audit\Domain\ValueObject\AuditActor;
use App\System\Audit\Domain\ValueObject\AuditCategory;
use App\System\Audit\Domain\ValueObject\AuditDetail;
use App\System\Audit\Domain\ValueObject\AuditEntryId;
use PHPUnit\Framework\TestCase;

final class AuditEntryTest extends TestCase
{
    private const string ID     = '01960000-0000-7000-8000-000000000e01';
    private const string ADMIN  = '01960000-0000-7000-8000-000000000a01';
    private const string CLINIC = '01960000-0000-7000-8000-000000000c01';
    private const string USER   = '01960000-0000-7000-8000-000000000b01';

    public function testAnEntryKeepsEverythingItWasGiven(): void
    {
        $at      = new \DateTimeImmutable('2026-11-01T02:32:00Z');
        $details = [AuditDetail::of('Durée', '18 min'), AuditDetail::of('Mode', 'Lecture seule')];

        $entry = AuditEntry::record(
            id: AuditEntryId::fromString(self::ID),
            occurredAt: $at,
            actor: AuditActor::kiveto(self::ADMIN, 'Djamil Soidiki'),
            action: AuditAction::fromString('support.session_ended'),
            category: AuditCategory::SUPPORT,
            summary: ' Session support terminée ',
            clinicId: self::CLINIC,
            clinicName: ' Clinique du Prado ',
            targetUserId: self::USER,
            ipAddress: '91.12.34.12',
            details: $details,
        );

        self::assertSame(self::ID, $entry->id()->toString());
        self::assertSame($at, $entry->occurredAt());
        self::assertTrue($entry->actor()->equals(AuditActor::kiveto(self::ADMIN, 'Djamil Soidiki')));
        self::assertSame('support.session_ended', $entry->action()->toString());
        self::assertSame(AuditCategory::SUPPORT, $entry->category());
        self::assertSame('Session support terminée', $entry->summary());
        self::assertSame(self::CLINIC, $entry->clinicId());
        self::assertSame('Clinique du Prado', $entry->clinicName());
        self::assertSame(self::USER, $entry->targetUserId());
        self::assertSame('91.12.34.12', $entry->ipAddress());
        self::assertSame($details, $entry->details());
    }

    public function testTheOptionalPartsDefaultToNothing(): void
    {
        $entry = $this->minimal(summary: 'Connexion au backoffice', clinicName: '  ');

        self::assertNull($entry->clinicId());
        self::assertNull($entry->clinicName(), 'a blank clinic name is no name');
        self::assertNull($entry->targetUserId());
        self::assertNull($entry->ipAddress());
        self::assertSame([], $entry->details());
    }

    public function testAnIpv6AddressIsAccepted(): void
    {
        self::assertSame('2001:db8::1', $this->minimal(ip: '2001:db8::1')->ipAddress());
    }

    public function testLongTextsAreCut(): void
    {
        $entry = $this->minimal(summary: str_repeat('s', 300), clinicName: str_repeat('c', 200));

        self::assertSame(255, mb_strlen($entry->summary()));
        self::assertSame(160, mb_strlen((string) $entry->clinicName()));
    }

    public function testASummaryIsRequired(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->minimal(summary: '   ');
    }

    public function testAnIpMustBeAnAddress(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->minimal(ip: '91.x.x.12');
    }

    private function minimal(string $summary = 'Connexion', ?string $clinicName = null, ?string $ip = null): AuditEntry
    {
        return AuditEntry::record(
            id: AuditEntryId::fromString(self::ID),
            occurredAt: new \DateTimeImmutable('2026-11-01T02:11:00Z'),
            actor: AuditActor::unknown(),
            action: AuditAction::fromString('admin.login'),
            category: AuditCategory::ACCESS,
            summary: $summary,
            clinicName: $clinicName,
            ipAddress: $ip,
        );
    }
}
