<?php

declare(strict_types=1);

namespace App\Tests\Unit\Clinic\Domain\ValueObject;

use App\Clinic\Domain\ValueObject\ClinicSlug;
use PHPUnit\Framework\TestCase;

final class ClinicSlugTest extends TestCase
{
    public function testFromStringWithValidSlug(): void
    {
        $slug = ClinicSlug::fromString('my-clinic');

        self::assertSame('my-clinic', $slug->toString());
        self::assertSame('my-clinic', (string) $slug);
    }

    public function testFromStringRejectsEmptyValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Clinic slug cannot be empty');

        ClinicSlug::fromString('');
    }

    public function testFromStringRejectsInvalidCharacters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid clinic slug format');

        ClinicSlug::fromString('My Clinic');
    }

    public function testFromStringRejectsUppercase(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid clinic slug format');

        ClinicSlug::fromString('My-Clinic');
    }

    public function testFromStringAcceptsHyphensAndNumbers(): void
    {
        $slug = ClinicSlug::fromString('clinic-123');

        self::assertSame('clinic-123', $slug->toString());
    }

    public function testEquals(): void
    {
        $slugA = ClinicSlug::fromString('clinic-a');
        $slugB = ClinicSlug::fromString('clinic-a');
        $slugC = ClinicSlug::fromString('clinic-b');

        self::assertTrue($slugA->equals($slugB));
        self::assertFalse($slugA->equals($slugC));
    }
}
