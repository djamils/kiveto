<?php

declare(strict_types=1);

namespace App\Tests\Unit\Clinic\Domain\ValueObject;

use App\Clinic\Domain\ValueObject\ClinicId;
use PHPUnit\Framework\TestCase;

final class ClinicIdTest extends TestCase
{
    public function testFromStringCreatesValidId(): void
    {
        $uuid = '018f1b1e-1234-7890-abcd-0123456789ab';
        $id   = ClinicId::fromString($uuid);

        self::assertSame($uuid, $id->toString());
        self::assertSame($uuid, (string) $id);
    }

    public function testFromStringRejectsEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ClinicId::fromString('');
    }

    public function testEqualsReturnsTrueForSameId(): void
    {
        $uuid = '018f1b1e-1234-7890-abcd-0123456789ab';
        $id1  = ClinicId::fromString($uuid);
        $id2  = ClinicId::fromString($uuid);

        self::assertTrue($id1->equals($id2));
    }

    public function testEqualsReturnsFalseForDifferentIds(): void
    {
        $id1 = ClinicId::fromString('018f1b1e-1111-7890-abcd-0123456789ab');
        $id2 = ClinicId::fromString('018f1b1e-2222-7890-abcd-0123456789ab');

        self::assertFalse($id1->equals($id2));
    }
}
