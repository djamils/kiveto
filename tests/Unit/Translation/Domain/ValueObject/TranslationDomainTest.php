<?php

declare(strict_types=1);

namespace App\Tests\Unit\Translation\Domain\ValueObject;

use App\Translation\Domain\ValueObject\TranslationDomain;
use PHPUnit\Framework\TestCase;

final class TranslationDomainTest extends TestCase
{
    public function testValid(): void
    {
        self::assertSame('messages', TranslationDomain::fromString('Messages')->toString());
        self::assertSame('auth.login', TranslationDomain::fromString('auth.login')->toString());
    }

    public function testInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TranslationDomain::fromString('invalid domain');
    }

    public function testEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TranslationDomain::fromString('');
    }

    public function testTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TranslationDomain::fromString(str_repeat('a', 70));
    }
}
