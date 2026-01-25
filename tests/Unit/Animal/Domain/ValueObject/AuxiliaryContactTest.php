<?php

declare(strict_types=1);

namespace App\Tests\Unit\Animal\Domain\ValueObject;

use App\Animal\Domain\ValueObject\AuxiliaryContact;
use PHPUnit\Framework\TestCase;

final class AuxiliaryContactTest extends TestCase
{
    public function testConstruction(): void
    {
        $contact = new AuxiliaryContact(
            firstName: 'John',
            lastName: 'Doe',
            phoneNumber: '+33612345678'
        );

        self::assertSame('John', $contact->firstName);
        self::assertSame('Doe', $contact->lastName);
        self::assertSame('+33612345678', $contact->phoneNumber);
    }
}
