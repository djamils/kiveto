<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Inventory\Domain\Shared\ValueObject;

use App\Context\Inventory\Domain\Shared\Exception\InvalidClinicIdException;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use PHPUnit\Framework\TestCase;

final class ClinicIdTest extends TestCase
{
    public function testValidUuidV4IsAccepted(): void
    {
        $id = ClinicId::fromString('550e8400-e29b-41d4-a716-446655440000');

        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $id->toString());
    }

    public function testValidUuidV7IsAccepted(): void
    {
        // UUID v7 still matches the UUID_REGEX (which only requires [89ab] in the variant nibble)
        $id = ClinicId::fromString('01970001-0000-7000-8000-000000000001');

        self::assertSame('01970001-0000-7000-8000-000000000001', $id->toString());
    }

    public function testNonUuidStringIsRejected(): void
    {
        $this->expectException(InvalidClinicIdException::class);

        ClinicId::fromString('not-a-uuid');
    }

    public function testEmptyStringIsRejected(): void
    {
        $this->expectException(InvalidClinicIdException::class);

        ClinicId::fromString('');
    }
}
