<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\ClinicalCare\Infrastructure\Adapter\Animal;

use App\Context\ClinicalCare\Application\Port\AnimalExistenceCheckerInterface;
use App\Context\ClinicalCare\Domain\ValueObject\AnimalId;
use App\Fixtures\Context\Animal\Factory\AnimalEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class DbalAnimalExistenceCheckerTest extends KernelTestCase
{
    use Factories;

    public function testReturnsTrueWhenAnimalExists(): void
    {
        $animalId = '11111111-1111-4111-8111-111111111111';

        AnimalEntityFactory::new()->withId($animalId)->create();

        $checker = self::getContainer()->get(AnimalExistenceCheckerInterface::class);
        \assert($checker instanceof AnimalExistenceCheckerInterface);

        self::assertTrue($checker->exists(AnimalId::fromString($animalId)));
    }

    public function testReturnsFalseWhenAnimalDoesNotExist(): void
    {
        $checker = self::getContainer()->get(AnimalExistenceCheckerInterface::class);
        \assert($checker instanceof AnimalExistenceCheckerInterface);

        self::assertFalse(
            $checker->exists(AnimalId::fromString('00000000-0000-4000-8000-000000000000')),
        );
    }
}
