<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Scheduling\Infrastructure\Adapter\Animal;

use App\Context\Scheduling\Application\Port\AnimalExistenceCheckerInterface;
use App\Context\Scheduling\Domain\ValueObject\AnimalId;
use App\Fixtures\Context\Animal\Factory\AnimalEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class DbalAnimalExistenceCheckerTest extends KernelTestCase
{
    use Factories;

    public function testReturnsTrueWhenAnimalExists(): void
    {
        $animalId = '01234567-89ab-cdef-0123-456789abcdef';

        AnimalEntityFactory::new()
            ->withId($animalId)
            ->create()
        ;

        $checker = self::getContainer()->get(AnimalExistenceCheckerInterface::class);
        \assert($checker instanceof AnimalExistenceCheckerInterface);

        self::assertTrue($checker->exists(AnimalId::fromString($animalId)));
    }

    public function testReturnsFalseWhenAnimalDoesNotExist(): void
    {
        $checker = self::getContainer()->get(AnimalExistenceCheckerInterface::class);
        \assert($checker instanceof AnimalExistenceCheckerInterface);

        self::assertFalse(
            $checker->exists(AnimalId::fromString('00000000-0000-0000-0000-000000000000')),
        );
    }
}
