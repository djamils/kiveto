<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Clinic\ViewModel\Directory;

use App\Presentation\Clinic\ViewModel\Directory\DirectoryAnimalInput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DirectoryAnimalInputTest extends TestCase
{
    private const array OK = ['nom' => 'Caramel', 'esp' => 'lapin', 'race' => 'Bélier nain', 'sexe' => 'F', 'naiss' => '14/03/2022'];

    private \DateTimeImmutable $today;

    protected function setUp(): void
    {
        $this->today = new \DateTimeImmutable('2026-10-16 10:00:00');
    }

    public function testARabbitIsANacWhoseBreedSaysSo(): void
    {
        $animal = DirectoryAnimalInput::parse(self::OK + ['ster' => true, 'puce' => '250 269 800 771 002'], $this->today);

        self::assertInstanceOf(DirectoryAnimalInput::class, $animal);
        self::assertSame('🐇', $animal->emoji());
        $dto = $animal->toDto();
        self::assertSame(['Caramel', 'nac', 'female', 'neutered', false, 'Lapin bélier nain', '2022-03-14', '250269800771002'], [
            $dto->name, $dto->species, $dto->sex, $dto->reproductiveStatus, $dto->isMixedBreed, $dto->breedName, $dto->birthDate, $dto->microchipNumber,
        ]);

        $command = $animal->toCreateAnimal('clinic', 'owner');
        self::assertSame(['clinic', 'owner', 'nac', 'Lapin bélier nain'], [$command->clinicId, $command->primaryOwnerClientId, $command->species, $command->breedName]);
    }

    public function testAMixedBreedWithAnEstimatedAge(): void
    {
        $dog = DirectoryAnimalInput::parse(['nom' => 'Rex', 'esp' => 'chien', 'race' => 'Croisé', 'sexe' => 'M', 'inconnue' => true, 'age' => '3'], $this->today);
        self::assertInstanceOf(DirectoryAnimalInput::class, $dog);
        self::assertSame(['dog', 'male', 'intact', true, 'Croisé', '2023-10-01', null], [
            $dog->toDto()->species, $dog->toDto()->sex, $dog->toDto()->reproductiveStatus, $dog->toDto()->isMixedBreed, $dog->toDto()->breedName, $dog->toDto()->birthDate, $dog->toDto()->microchipNumber,
        ]);

        $kit = DirectoryAnimalInput::parse(['nom' => 'Pompon', 'esp' => 'lapin', 'race' => 'Croisé', 'sexe' => 'M', 'inconnue' => true, 'age' => '0'], $this->today);
        self::assertInstanceOf(DirectoryAnimalInput::class, $kit);
        self::assertSame(['Lapin croisé', '2026-04-01'], [$kit->toDto()->breedName, $kit->toDto()->birthDate]);
    }

    public function testAnUntouchedAnimalIsNone(): void
    {
        self::assertTrue(DirectoryAnimalInput::isBlank(['race' => 'Croisé', 'ster' => true, 'nom' => ' ']));
        self::assertFalse(DirectoryAnimalInput::isBlank(['sexe' => 'M']));
    }

    /**
     * @param array<string, string|bool> $raw
     */
    #[DataProvider('provideSaysTheFirstThingMissingCases')]
    public function testSaysTheFirstThingMissing(array $raw, string $message): void
    {
        self::assertSame($message, DirectoryAnimalInput::parse($raw, $this->today));
    }

    /**
     * @return iterable<string, array{array<string, string|bool>, string}>
     */
    public static function provideSaysTheFirstThingMissingCases(): iterable
    {
        yield 'name' => [['esp' => 'chat'], 'Nom de l\'animal requis'];
        yield 'species' => [['nom' => 'Mia', 'esp' => 'cheval'], 'Espèce requise'];
        yield 'breed' => [['nom' => 'Mia', 'esp' => 'chat', 'race' => 'Beagle'], 'Race à choisir dans la liste'];
        yield 'sex' => [['nom' => 'Mia', 'esp' => 'chat', 'race' => 'Européen'], 'Sexe requis'];
        yield 'no date' => [['nom' => 'Mia', 'esp' => 'chat', 'race' => 'Européen', 'sexe' => 'F', 'naiss' => '12/03'], 'Date de naissance, ou « date inconnue »'];
        yield 'no age' => [['nom' => 'Mia', 'esp' => 'chat', 'race' => 'Européen', 'sexe' => 'F', 'inconnue' => true, 'age' => '4'], 'Âge estimé requis'];
        yield 'no such date' => [['naiss' => '31/02/2022'] + self::OK, 'Date inexistante'];
        yield 'future' => [['naiss' => '17/10/2026'] + self::OK, 'Date dans le futur'];
        yield 'too old' => [['naiss' => '01/01/1990'] + self::OK, 'Plus de 35 ans : vérifiez l\'année'];
        yield 'chip' => [self::OK + ['puce' => '25026980077'], 'Puce incomplète (15 chiffres)'];
    }
}
