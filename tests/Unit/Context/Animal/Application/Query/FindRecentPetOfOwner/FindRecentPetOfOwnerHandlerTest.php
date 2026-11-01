<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Animal\Application\Query\FindRecentPetOfOwner;

use App\Context\Animal\Application\Port\AnimalReadRepositoryInterface;
use App\Context\Animal\Application\Query\FindRecentPetOfOwner\FindRecentPetOfOwner;
use App\Context\Animal\Application\Query\FindRecentPetOfOwner\FindRecentPetOfOwnerHandler;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use PHPUnit\Framework\TestCase;

final class FindRecentPetOfOwnerHandlerTest extends TestCase
{
    private const string CLINIC_ID = '01950000-0000-7000-0000-000000000020';
    private const string CLIENT_ID = '01950000-0000-7000-0000-000000000040';
    private const string ANIMAL_ID = '01950000-0000-7000-0000-000000000030';

    public function testAsksTheReadRepository(): void
    {
        $since          = new \DateTimeImmutable('2026-04-12 06:00:00');
        $readRepository = $this->createMock(AnimalReadRepositoryInterface::class);
        $readRepository
            ->expects(self::once())
            ->method('findRecentPetOfOwner')
            ->with(self::callback(static fn (ClinicId $id): bool => self::CLINIC_ID === $id->toString()), self::CLIENT_ID, 'Pixel', 'cat', $since)
            ->willReturn(self::ANIMAL_ID)
        ;

        $id = new FindRecentPetOfOwnerHandler($readRepository)(new FindRecentPetOfOwner(self::CLINIC_ID, self::CLIENT_ID, 'Pixel', 'cat', $since));

        self::assertSame(self::ANIMAL_ID, $id);
    }
}
