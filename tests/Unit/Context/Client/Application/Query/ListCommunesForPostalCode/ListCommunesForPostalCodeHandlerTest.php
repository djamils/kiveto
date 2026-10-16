<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Query\ListCommunesForPostalCode;

use App\Context\Client\Application\Port\AddressDirectoryInterface;
use App\Context\Client\Application\Query\ListCommunesForPostalCode\ListCommunesForPostalCode;
use App\Context\Client\Application\Query\ListCommunesForPostalCode\ListCommunesForPostalCodeHandler;
use PHPUnit\Framework\TestCase;

final class ListCommunesForPostalCodeHandlerTest extends TestCase
{
    public function testListsTheCommunesOfAPostalCode(): void
    {
        $directory = $this->createMock(AddressDirectoryInterface::class);
        $directory->expects(self::once())->method('communesOf')->with('13600')->willReturn(['Ceyreste', 'La Ciotat']);

        self::assertSame(['Ceyreste', 'La Ciotat'], (new ListCommunesForPostalCodeHandler($directory))(new ListCommunesForPostalCode('13600')));
    }

    public function testSaysWhenTheDirectoryCannotBeReached(): void
    {
        $directory = $this->createStub(AddressDirectoryInterface::class);
        $directory->method('communesOf')->willReturn(null);

        self::assertNull((new ListCommunesForPostalCodeHandler($directory))(new ListCommunesForPostalCode('75011')));
    }

    public function testAnythingButFiveDigitsServesNoCommune(): void
    {
        $directory = $this->createMock(AddressDirectoryInterface::class);
        $directory->expects(self::never())->method('communesOf');

        self::assertSame([], (new ListCommunesForPostalCodeHandler($directory))(new ListCommunesForPostalCode('1360')));
    }
}
