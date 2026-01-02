<?php

declare(strict_types=1);

namespace App\Tests\Unit\Translation\Domain\ValueObject;

use App\Translation\Domain\ValueObject\AppScope;
use PHPUnit\Framework\TestCase;

final class AppScopeTest extends TestCase
{
    public function testFromStringOk(): void
    {
        self::assertSame(AppScope::CLINIC, AppScope::fromString('clinic'));
        self::assertSame(AppScope::PORTAL, AppScope::fromString('PORTAL'));
        self::assertSame(AppScope::BACKOFFICE, AppScope::fromString('BackOffice'));
        self::assertSame(AppScope::SHARED, AppScope::fromString('shared'));
    }

    public function testFromStringInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        AppScope::fromString('invalid');
    }
}
