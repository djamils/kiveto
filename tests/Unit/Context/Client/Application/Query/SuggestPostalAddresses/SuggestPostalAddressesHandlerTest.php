<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Query\SuggestPostalAddresses;

use App\Context\Client\Application\Port\AddressDirectoryInterface;
use App\Context\Client\Application\Port\AddressSuggestion;
use App\Context\Client\Application\Query\SuggestPostalAddresses\SuggestPostalAddresses;
use App\Context\Client\Application\Query\SuggestPostalAddresses\SuggestPostalAddressesHandler;
use PHPUnit\Framework\TestCase;

final class SuggestPostalAddressesHandlerTest extends TestCase
{
    public function testAsksTheDirectoryNearTheClinic(): void
    {
        $found     = [new AddressSuggestion('12 Rue de Rome', '13001', 'Marseille')];
        $directory = $this->createMock(AddressDirectoryInterface::class);
        $directory->expects(self::once())->method('suggest')->with('12 rue de rome', 6, '13001')->willReturn($found);

        self::assertSame($found, (new SuggestPostalAddressesHandler($directory))(new SuggestPostalAddresses("  12   rue\tde rome ", 6, '13001')));
    }

    public function testCapsTheLimitAndIgnoresAnInvalidPostalCode(): void
    {
        $directory = $this->createMock(AddressDirectoryInterface::class);
        $directory->expects(self::exactly(2))->method('suggest')->willReturnCallback(
            static function (string $query, int $limit, ?string $near): array {
                self::assertContains($limit, [1, SuggestPostalAddresses::MAX_LIMIT]);
                self::assertNull($near);

                return [];
            },
        );
        $handler = new SuggestPostalAddressesHandler($directory);

        self::assertSame([], $handler(new SuggestPostalAddresses('rue de rome', 50, '130')));
        self::assertSame([], $handler(new SuggestPostalAddresses('rue de rome', 0)));
    }

    public function testTooShortAQueryFindsNothing(): void
    {
        $directory = $this->createMock(AddressDirectoryInterface::class);
        $directory->expects(self::never())->method('suggest');

        self::assertSame([], (new SuggestPostalAddressesHandler($directory))(new SuggestPostalAddresses(' 12 ')));
    }
}
