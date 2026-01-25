<?php

declare(strict_types=1);

namespace App\Tests\Unit\Client\Application\Query\GetClientById;

use App\Client\Application\Query\GetClientById\ContactMethodDto;
use PHPUnit\Framework\TestCase;

final class ContactMethodDtoTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $dto = new ContactMethodDto(
            type: 'phone',
            label: 'mobile',
            value: '+33612345678',
            isPrimary: true
        );

        self::assertSame('phone', $dto->type);
        self::assertSame('mobile', $dto->label);
        self::assertSame('+33612345678', $dto->value);
        self::assertTrue($dto->isPrimary);
    }
}
