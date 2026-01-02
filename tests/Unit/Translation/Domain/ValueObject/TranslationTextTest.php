<?php

declare(strict_types=1);

namespace App\Tests\Unit\Translation\Domain\ValueObject;

use App\Translation\Domain\ValueObject\TranslationText;
use PHPUnit\Framework\TestCase;

final class TranslationTextTest extends TestCase
{
    public function testValid(): void
    {
        self::assertSame(' Hello ', TranslationText::fromString(' Hello ')->toString());
    }

    public function testEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TranslationText::fromString('');
    }
}
