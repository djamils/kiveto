<?php

declare(strict_types=1);

namespace App\Tests\Unit\Clinic\Domain\ValueObject;

use App\Clinic\Domain\ValueObject\ClinicGroupId;
use PHPUnit\Framework\TestCase;

final class ClinicGroupIdTest extends TestCase
{
    public function testFromStringCreatesValidId(): void
    {
        $uuid = '018f1b1e-1234-7890-abcd-0123456789ab';
        $id   = ClinicGroupId::fromString($uuid);

        self::assertSame($uuid, $id->toString());
        self::assertSame($uuid, (string) $id);
    }

    public function testFromStringRejectsEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ClinicGroupId::fromString('');
    }

    public function testEqualsReturnsTrueForSameId(): void
    {
        $uuid = '018f1b1e-1234-7890-abcd-0123456789ab';
        $id1  = ClinicGroupId::fromString($uuid);
        $id2  = ClinicGroupId::fromString($uuid);

        self::assertTrue($id1->equals($id2));
    }

    public function testEqualsReturnsFalseForDifferentIds(): void
    {
        $id1 = ClinicGroupId::fromString('018f1b1e-1111-7890-abcd-0123456789ab');
        $id2 = ClinicGroupId::fromString('018f1b1e-2222-7890-abcd-0123456789ab');

        self::assertFalse($id1->equals($id2));
    }
}
