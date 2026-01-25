<?php

declare(strict_types=1);

namespace App\Tests\Unit\Client\Application\Command\ReplaceClientContactMethods;

use App\Client\Application\Command\ReplaceClientContactMethods\ContactMethodDto;
use PHPUnit\Framework\TestCase;

final class ContactMethodDtoTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $dto = new ContactMethodDto(
            type: 'email',
            label: 'work',
            value: 'john@example.com',
            isPrimary: false
        );

        self::assertSame('email', $dto->type);
        self::assertSame('work', $dto->label);
        self::assertSame('john@example.com', $dto->value);
        self::assertFalse($dto->isPrimary);
    }

    public function testConstructorDefaultsIsPrimaryToFalse(): void
    {
        $dto = new ContactMethodDto(
            type: 'phone',
            label: 'home',
            value: '+33612345678'
        );

        self::assertFalse($dto->isPrimary);
    }
}
