<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Domain\Staff\ValueObject;

use App\Context\Clinic\Domain\Staff\ValueObject\StaffProfileId;
use PHPUnit\Framework\TestCase;

final class StaffProfileIdTest extends TestCase
{
    public function testFromStringWithValidUuid(): void
    {
        $id = StaffProfileId::fromString('01912345-6789-7abc-8def-000000000001');

        self::assertSame('01912345-6789-7abc-8def-000000000001', $id->toString());
        self::assertSame('01912345-6789-7abc-8def-000000000001', (string) $id);
    }

    public function testFromStringRejectsEmptyValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('StaffProfileId cannot be empty.');

        StaffProfileId::fromString('');
    }

    public function testFromStringRejectsWhitespaceOnly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('StaffProfileId cannot be empty.');

        StaffProfileId::fromString('   ');
    }

    public function testFromStringRejectsInvalidUuid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid StaffProfileId');

        StaffProfileId::fromString('not-a-uuid');
    }

    public function testEquals(): void
    {
        $a = StaffProfileId::fromString('01912345-6789-7abc-8def-000000000001');
        $b = StaffProfileId::fromString('01912345-6789-7abc-8def-000000000001');
        $c = StaffProfileId::fromString('01912345-6789-7abc-8def-000000000002');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
