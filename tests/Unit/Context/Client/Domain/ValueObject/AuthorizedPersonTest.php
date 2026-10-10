<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Domain\ValueObject;

use App\Context\Client\Domain\ValueObject\AuthorizedPerson;
use App\Context\Client\Domain\ValueObject\AuthorizedPersonRelationship;
use App\Context\Client\Domain\ValueObject\Civility;
use App\Shared\Domain\ValueObject\PhoneNumber;
use PHPUnit\Framework\TestCase;

final class AuthorizedPersonTest extends TestCase
{
    public function testHoldsTrimmedNamesAndDetails(): void
    {
        $authorizedAt = new \DateTimeImmutable('2026-03-14 10:00:00');
        $phone        = PhoneNumber::fromString('+33688214509');

        $person = new AuthorizedPerson(
            Civility::MISTER,
            '  Marc ',
            ' Lambert ',
            AuthorizedPersonRelationship::SPOUSE,
            $phone,
            $authorizedAt,
        );

        self::assertSame(Civility::MISTER, $person->civility);
        self::assertSame('Marc', $person->firstName);
        self::assertSame('Lambert', $person->lastName);
        self::assertSame(AuthorizedPersonRelationship::SPOUSE, $person->relationship);
        self::assertSame($phone, $person->phoneNumber);
        self::assertSame($authorizedAt, $person->authorizedAt);
        self::assertSame('Marc Lambert', $person->fullName());
    }

    public function testRejectsEmptyFirstName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new AuthorizedPerson(
            Civility::UNSPECIFIED,
            '   ',
            'Lambert',
            AuthorizedPersonRelationship::OTHER,
            null,
            new \DateTimeImmutable(),
        );
    }

    public function testRejectsEmptyLastName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new AuthorizedPerson(
            Civility::UNSPECIFIED,
            'Marc',
            '',
            AuthorizedPersonRelationship::OTHER,
            null,
            new \DateTimeImmutable(),
        );
    }

    public function testSamePersonIgnoresCaseAndEverythingButTheName(): void
    {
        $marc = new AuthorizedPerson(
            Civility::MISTER,
            'Marc',
            'Lambert',
            AuthorizedPersonRelationship::SPOUSE,
            null,
            new \DateTimeImmutable('2022-03-14'),
        );
        $sameMarc = new AuthorizedPerson(
            Civility::UNSPECIFIED,
            'MARC',
            'lambert',
            AuthorizedPersonRelationship::OTHER,
            PhoneNumber::fromString('+33688214509'),
            new \DateTimeImmutable('2026-01-01'),
        );
        $julie = new AuthorizedPerson(
            Civility::MADAM,
            'Julie',
            'Lambert',
            AuthorizedPersonRelationship::CHILD,
            null,
            new \DateTimeImmutable('2022-03-14'),
        );

        self::assertTrue($marc->isSamePersonAs($sameMarc));
        self::assertFalse($marc->isSamePersonAs($julie));
    }
}
