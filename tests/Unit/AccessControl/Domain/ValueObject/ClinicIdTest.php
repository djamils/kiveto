<?php

declare(strict_types=1);

namespace App\Tests\Unit\AccessControl\Domain\ValueObject;

use App\AccessControl\Domain\ValueObject\ClinicId;
use PHPUnit\Framework\TestCase;

final class ClinicIdTest extends TestCase
{
    public function testFromString(): void
    {
        $uuid     = '11111111-1111-1111-1111-111111111111';
        $clinicId = ClinicId::fromString($uuid);

        self::assertSame($uuid, $clinicId->toString());
    }

    public function testEquals(): void
    {
        $clinicId1 = ClinicId::fromString('11111111-1111-1111-1111-111111111111');
        $clinicId2 = ClinicId::fromString('11111111-1111-1111-1111-111111111111');
        $clinicId3 = ClinicId::fromString('22222222-2222-2222-2222-222222222222');

        self::assertTrue($clinicId1->equals($clinicId2));
        self::assertFalse($clinicId1->equals($clinicId3));
    }

    public function testThrowsExceptionWhenEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Identifier cannot be empty.');

        ClinicId::fromString('');
    }
}
