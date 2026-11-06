<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Audit\Domain\ValueObject;

use App\System\Audit\Domain\ValueObject\AuditAction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AuditActionTest extends TestCase
{
    public function testADottedCodeIsKept(): void
    {
        self::assertSame('support.session_started', AuditAction::fromString('support.session_started')->toString());
        self::assertSame('auth.2fa_enabled', AuditAction::fromString('auth.2fa_enabled')->toString());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function provideAnythingElseIsRefusedCases(): iterable
    {
        yield 'no dot' => ['login'];
        yield 'upper case' => ['Clinic.created'];
        yield 'trailing dot' => ['clinic.'];
        yield 'leading digit' => ['1clinic.created'];
        yield 'space' => ['clinic created.x'];
        yield 'over 64 chars' => ['a.' . str_repeat('b', 63)];
    }

    #[DataProvider('provideAnythingElseIsRefusedCases')]
    public function testAnythingElseIsRefused(string $code): void
    {
        $this->expectException(\InvalidArgumentException::class);

        AuditAction::fromString($code);
    }

    public function testEqualityComparesTheCode(): void
    {
        self::assertTrue(AuditAction::fromString('clinic.created')->equals(AuditAction::fromString('clinic.created')));
        self::assertFalse(AuditAction::fromString('clinic.created')->equals(AuditAction::fromString('clinic.suspended')));
    }
}
