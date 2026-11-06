<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Audit\Domain\ValueObject;

use App\System\Audit\Domain\ValueObject\ActorKind;
use App\System\Audit\Domain\ValueObject\AuditActor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AuditActorTest extends TestCase
{
    private const string ID = '01960000-0000-7000-8000-000000000a01';

    public function testAKivetoAdminKeepsItsIdAndTrimmedName(): void
    {
        $actor = AuditActor::kiveto(self::ID, '  Djamil Soidiki ');

        self::assertSame(ActorKind::KIVETO, $actor->kind());
        self::assertSame(self::ID, $actor->id());
        self::assertSame('Djamil Soidiki', $actor->name());
    }

    public function testAStaffMemberIsIdentified(): void
    {
        $actor = AuditActor::staff(self::ID, 'Dr Claire Martin');

        self::assertSame(ActorKind::STAFF, $actor->kind());
        self::assertSame(self::ID, $actor->id());
        self::assertSame('Dr Claire Martin', $actor->name());
    }

    public function testTheSystemHasANameButNoId(): void
    {
        $actor = AuditActor::system('Import ANMV');

        self::assertSame(ActorKind::SYSTEM, $actor->kind());
        self::assertNull($actor->id());
        self::assertSame('Import ANMV', $actor->name());
    }

    public function testAnUnknownCallerHasNeitherIdNorName(): void
    {
        $actor = AuditActor::unknown();

        self::assertSame(ActorKind::UNKNOWN, $actor->kind());
        self::assertNull($actor->id());
        self::assertNull($actor->name());
    }

    public function testALongNameIsCutAt160Characters(): void
    {
        self::assertSame(160, mb_strlen((string) AuditActor::staff(self::ID, str_repeat('é', 200))->name()));
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function provideAnIdentifiedActorNeedsAnIdAndANameCases(): iterable
    {
        yield 'blank id' => ['  ', 'Djamil'];
        yield 'blank name' => [self::ID, ' '];
    }

    #[DataProvider('provideAnIdentifiedActorNeedsAnIdAndANameCases')]
    public function testAnIdentifiedActorNeedsAnIdAndAName(string $id, string $name): void
    {
        $this->expectException(\InvalidArgumentException::class);

        AuditActor::kiveto($id, $name);
    }

    public function testTheSystemNeedsAName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        AuditActor::system('');
    }

    public function testEqualityComparesKindIdAndName(): void
    {
        $actor = AuditActor::kiveto(self::ID, 'Djamil Soidiki');

        self::assertTrue($actor->equals(AuditActor::kiveto(self::ID, 'Djamil Soidiki')));
        self::assertFalse($actor->equals(AuditActor::staff(self::ID, 'Djamil Soidiki')));
        self::assertFalse($actor->equals(AuditActor::kiveto(self::ID, 'D. Soidiki')));
        self::assertTrue(AuditActor::unknown()->equals(AuditActor::unknown()));
    }
}
