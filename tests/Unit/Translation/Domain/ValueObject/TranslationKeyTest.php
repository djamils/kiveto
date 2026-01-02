<?php

declare(strict_types=1);

namespace App\Tests\Unit\Translation\Domain\ValueObject;

use App\Translation\Domain\ValueObject\TranslationKey;
use PHPUnit\Framework\TestCase;

final class TranslationKeyTest extends TestCase
{
    public function testValid(): void
    {
        self::assertSame('foo.bar', TranslationKey::fromString('Foo.Bar')->toString());
    }

    public function testInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TranslationKey::fromString('invalid key with space');
    }

    public function testEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TranslationKey::fromString('');
    }

    public function testTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TranslationKey::fromString(str_repeat('a', 200));
    }
}
