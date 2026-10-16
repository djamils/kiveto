<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Directory;

use App\Context\Animal\Application\Command\CreateAnimal\CreateAnimal;
use App\Context\Client\Application\Command\CreateClient\NewAnimalDto;

/**
 * An animal as the directory's « Nouveau client » and « Nouvel animal »
 * windows describe it: closed lists for the species, the breed and the
 * estimated age, a birth date typed « JJ/MM/AAAA » or unknown, a 15-digit
 * chip. The lists are the windows' own, handed to the page module, and the
 * same checks run here before anything is recorded.
 *
 * Rabbits and birds have no species of their own in the animal record: they
 * are kept as NAC, the breed saying the rest (« Lapin bélier nain »).
 */
final readonly class DirectoryAnimalInput
{
    /** Window key => [species of the record, label, emoji]. */
    public const array SPECIES = [
        'chien'  => ['dog', 'Chien', '🐕'],
        'chat'   => ['cat', 'Chat', '🐈'],
        'lapin'  => ['nac', 'Lapin', '🐇'],
        'oiseau' => ['nac', 'Oiseau', '🐦'],
        'nac'    => ['nac', 'NAC', '🐾'],
    ];

    public const string MIXED = 'Croisé';

    public const array BREEDS = [
        'chien'  => ['Croisé', 'Beagle', 'Berger allemand', 'Berger australien', 'Bichon maltais', 'Border collie', 'Bouledogue français', 'Boxer', 'Cavalier King Charles', 'Chihuahua', 'Cocker anglais', 'Golden retriever', 'Husky sibérien', 'Jack Russell terrier', 'Labrador retriever', 'Malinois', 'Shih tzu', 'Staffordshire bull terrier', 'Teckel', 'Yorkshire terrier'],
        'chat'   => ['Européen', 'Croisé', 'Bengal', 'British shorthair', 'Chartreux', 'Maine coon', 'Norvégien', 'Persan', 'Ragdoll', 'Sacré de Birmanie', 'Siamois', 'Sphynx'],
        'lapin'  => ['Croisé', 'Bélier nain', 'Nain de couleur', 'Rex', 'Tête de lion'],
        'oiseau' => ['Calopsitte', 'Canari', 'Gris du Gabon', 'Inséparable', 'Perruche ondulée'],
        'nac'    => ['Chinchilla', "Cochon d'Inde", 'Furet', 'Hamster', 'Octodon', 'Rat', 'Tortue terrestre'],
    ];

    /** Estimated age => [label, years back (months for the first)]. */
    public const array AGES = [
        '0'  => ["Moins d'un an", 0],
        '1'  => ['Environ 1 an', 1],
        '2'  => ['Environ 2 ans', 2],
        '3'  => ['Environ 3 ans', 3],
        '5'  => ['4 à 6 ans', 5],
        '8'  => ['7 à 10 ans', 8],
        '12' => ['Plus de 10 ans', 12],
    ];

    public const int MAX_AGE_YEARS = 35;

    private function __construct(
        public string $name,
        public string $speciesKey,
        public string $breed,
        public string $sex,
        public bool $neutered,
        public string $birthDate,
        public bool $estimated,
        public ?string $chip,
    ) {
    }

    /**
     * An animal left untouched in the window (no name, species, sex, date
     * nor chip) is not one: the client is created without it.
     *
     * @param array<array-key, mixed> $raw
     */
    public static function isBlank(array $raw): bool
    {
        foreach (['nom', 'esp', 'puce', 'naiss', 'sexe'] as $key) {
            if ('' !== self::text($raw, $key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The animal, or the first thing missing, in the window's words.
     *
     * @param array<array-key, mixed> $raw
     */
    public static function parse(array $raw, \DateTimeImmutable $today): self|string
    {
        $name    = mb_substr(self::text($raw, 'nom'), 0, 30);
        $species = self::text($raw, 'esp');
        $breed   = self::text($raw, 'race');
        $sex     = self::text($raw, 'sexe');
        $unknown = true === ($raw['inconnue'] ?? false);
        $chip    = (string) preg_replace('/\D+/', '', self::text($raw, 'puce'));

        if ('' === $name) {
            return 'Nom de l\'animal requis';
        }
        if (!isset(self::SPECIES[$species])) {
            return 'Espèce requise';
        }
        if (!\in_array($breed, self::BREEDS[$species], true)) {
            return 'Race à choisir dans la liste';
        }
        if (!\in_array($sex, ['M', 'F'], true)) {
            return 'Sexe requis';
        }

        if ($unknown) {
            $age = self::text($raw, 'age');
            if (!isset(self::AGES[$age])) {
                return 'Âge estimé requis';
            }
            $birthDate = self::estimatedBirthDate($age, $today);
        } else {
            $typed = self::text($raw, 'naiss');
            if (1 !== preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $typed, $m)) {
                return 'Date de naissance, ou « date inconnue »';
            }
            $error = self::birthDateError((int) $m[1], (int) $m[2], (int) $m[3], $today);
            if (null !== $error) {
                return $error;
            }
            $birthDate = \sprintf('%s-%s-%s', $m[3], $m[2], $m[1]);
        }

        if ('' !== $chip && 15 !== \strlen($chip)) {
            return 'Puce incomplète (15 chiffres)';
        }

        return new self($name, $species, $breed, $sex, true === ($raw['ster'] ?? false), $birthDate, $unknown, '' === $chip ? null : $chip);
    }

    public function emoji(): string
    {
        return self::SPECIES[$this->speciesKey][2];
    }

    public function toDto(): NewAnimalDto
    {
        return new NewAnimalDto(
            name: $this->name,
            species: self::SPECIES[$this->speciesKey][0],
            sex: 'M' === $this->sex ? 'male' : 'female',
            reproductiveStatus: $this->neutered ? 'neutered' : 'intact',
            isMixedBreed: self::MIXED === $this->breed,
            breedName: $this->breedName(),
            birthDate: $this->birthDate,
            microchipNumber: $this->chip,
        );
    }

    /**
     * The animal of a client already on file.
     */
    public function toCreateAnimal(string $clinicId, string $ownerClientId): CreateAnimal
    {
        $dto = $this->toDto();

        return new CreateAnimal(
            clinicId: $clinicId,
            name: $dto->name,
            species: $dto->species,
            sex: $dto->sex,
            reproductiveStatus: $dto->reproductiveStatus,
            isMixedBreed: $dto->isMixedBreed,
            breedName: $dto->breedName,
            birthDate: $dto->birthDate,
            color: null,
            photoUrl: null,
            microchipNumber: $dto->microchipNumber,
            tattooNumber: null,
            passportNumber: null,
            registryType: 'none',
            registryNumber: null,
            registryReference: null,
            lifeStatus: 'alive',
            deceasedAt: null,
            missingSince: null,
            transferStatus: 'none',
            soldAt: null,
            givenAt: null,
            auxiliaryContactFirstName: null,
            auxiliaryContactLastName: null,
            auxiliaryContactPhoneNumber: null,
            primaryOwnerClientId: $ownerClientId,
            secondaryOwnerClientIds: [],
        );
    }

    /**
     * The breed as the record keeps it; a rabbit's says it is one, since
     * its species is NAC.
     */
    private function breedName(): string
    {
        if ('lapin' !== $this->speciesKey) {
            return $this->breed;
        }

        return self::MIXED === $this->breed ? 'Lapin croisé' : 'Lapin ' . mb_strtolower($this->breed);
    }

    /**
     * « Environ 3 ans » becomes the first day of the month three years ago,
     * « Moins d'un an » six months ago: an approximate birth date the sheet
     * shows and the vet corrects later, as at the desk.
     */
    private static function estimatedBirthDate(string $age, \DateTimeImmutable $today): string
    {
        $years = self::AGES[$age][1];

        return (0 === $years ? $today->modify('-6 months') : $today->modify(\sprintf('-%d years', $years)))->format('Y-m-01');
    }

    private static function birthDateError(int $day, int $month, int $year, \DateTimeImmutable $today): ?string
    {
        if (!checkdate($month, $day, $year)) {
            return 'Date inexistante';
        }

        $date = $today->setDate($year, $month, $day)->setTime(0, 0);

        if ($date > $today->setTime(0, 0)) {
            return 'Date dans le futur';
        }

        if ((int) $today->format('Y') - $year > self::MAX_AGE_YEARS) {
            return 'Plus de 35 ans : vérifiez l\'année';
        }

        return null;
    }

    /**
     * @param array<array-key, mixed> $raw
     */
    private static function text(array $raw, string $key): string
    {
        $value = $raw[$key] ?? '';

        return \is_string($value) ? mb_trim($value) : '';
    }
}
