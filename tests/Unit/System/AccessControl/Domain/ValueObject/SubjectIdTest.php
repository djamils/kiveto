<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\AccessControl\Domain\ValueObject;

use App\System\AccessControl\Domain\ValueObject\SubjectId;
use PHPUnit\Framework\TestCase;

final class SubjectIdTest extends TestCase
{
    public function testFromStringAndToString(): void
    {
        $id = SubjectId::fromString('01912345-6789-7abc-8def-0123456789ab');

        self::assertSame('01912345-6789-7abc-8def-0123456789ab', $id->toString());
        self::assertSame('01912345-6789-7abc-8def-0123456789ab', (string) $id);
    }

    public function testEquals(): void
    {
        $a = SubjectId::fromString('01912345-6789-7abc-8def-0123456789ab');
        $b = SubjectId::fromString('01912345-6789-7abc-8def-0123456789ab');
        $c = SubjectId::fromString('01912345-6789-7abc-8def-0123456789ac');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }

    public function testEmptyThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SubjectId::fromString('');
    }
}
