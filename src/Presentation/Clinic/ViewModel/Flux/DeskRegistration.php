<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Flux;

use App\Context\Animal\Application\Command\CreateAnimal\CreateAnimal;
use App\Context\Client\Application\Command\CreateClient\ContactMethodDto;
use App\Context\Client\Application\Command\CreateClient\CreateClient;
use App\Context\Client\Application\Command\UpdateClientIdentity\UpdateClientIdentity;
use App\Shared\Application\Bus\CommandBusInterface;

/**
 * The desk's short registration of a newcomer — a walk-in nobody knows, a
 * patient identified after an emergency, an animal found and brought in:
 * an owner with a phone, an animal described in a few words, the record to
 * be completed later on the sheets.
 */
final readonly class DeskRegistration
{
    public const array SPECIES = ['dog', 'cat', 'nac', 'other'];

    public function __construct(
        private CommandBusInterface $commandBus,
    ) {
    }

    /**
     * @param 'mister'|'madam'|null $civility
     */
    public function createOwner(string $clinicId, string $firstName, string $lastName, string $phone, ?string $civility = null): string
    {
        $firstName = trim($firstName);
        $lastName  = trim($lastName);
        $phone     = trim($phone);

        if ('' === $firstName || '' === $lastName) {
            throw new \InvalidArgumentException('Indiquez le prénom et le nom du propriétaire.');
        }

        if ('' === $phone) {
            throw new \InvalidArgumentException('Le téléphone du propriétaire est obligatoire.');
        }

        $clientId = $this->commandBus->dispatch(new CreateClient(
            clinicId: $clinicId,
            firstName: $firstName,
            lastName: $lastName,
            contactMethods: [
                new ContactMethodDto(type: 'phone', label: 'mobile', value: self::e164($phone), isPrimary: true),
            ],
        ));
        \assert(\is_string($clientId));

        if (null !== $civility) {
            $this->commandBus->dispatch(new UpdateClientIdentity($clinicId, $clientId, $firstName, $lastName, $civility));
        }

        return $clientId;
    }

    /**
     * @param 'male'|'female'|'unknown' $sex
     */
    public function createAnimal(
        string $clinicId,
        string $ownerId,
        string $name,
        string $species,
        string $sex = 'unknown',
        ?string $birthDate = null,
        ?string $microchip = null,
    ): string {
        $animalId = $this->commandBus->dispatch(new CreateAnimal(
            clinicId: $clinicId,
            name: $name,
            species: \in_array($species, self::SPECIES, true) ? $species : 'other',
            sex: $sex,
            reproductiveStatus: 'unknown',
            isMixedBreed: false,
            breedName: null,
            birthDate: $birthDate,
            color: null,
            photoUrl: null,
            microchipNumber: $microchip,
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
            primaryOwnerClientId: $ownerId,
            secondaryOwnerClientIds: [],
        ));
        \assert(\is_string($animalId));

        return $animalId;
    }

    /** « 06 12 34 56 78 » as typed at the desk becomes « +33612345678 »; an international number is kept. */
    public static function e164(string $phone): string
    {
        $compact = preg_replace('/[\s.\-()]/', '', $phone) ?? $phone;

        if (str_starts_with($compact, '00')) {
            return '+' . substr($compact, 2);
        }

        if (1 === preg_match('/^0\d{9}$/', $compact)) {
            return '+33' . substr($compact, 1);
        }

        return $compact;
    }

    /**
     * « M. Paul Garcia » → civility, first name, last name; a single word is
     * the last name.
     *
     * @return array{civility: 'mister'|'madam'|null, firstName: string, lastName: string}
     */
    public static function splitName(string $fullName): array
    {
        $words    = preg_split('/\s+/u', trim($fullName), -1, \PREG_SPLIT_NO_EMPTY) ?: [];
        $civility = null;

        if ([] !== $words) {
            $civility = match (mb_strtolower($words[0])) {
                'm.', 'm', 'mr', 'mr.', 'monsieur' => 'mister',
                'mme', 'mme.', 'madame' => 'madam',
                default => null,
            };

            if (null !== $civility) {
                array_shift($words);
            }
        }

        $lastName = (string) array_pop($words);

        return ['civility' => $civility, 'firstName' => implode(' ', $words), 'lastName' => $lastName];
    }

    /**
     * An estimated age at the desk (« jeune », « adulte », « senior ») becomes
     * an approximate birth date the sheet shows and the vet corrects later.
     */
    public static function estimatedBirthDate(string $age, \DateTimeImmutable $today): ?string
    {
        return match ($age) {
            'jeune'  => $today->modify('-6 months')->format('Y-m-01'),
            'adulte' => $today->modify('-5 years')->format('Y-01-01'),
            'senior' => $today->modify('-11 years')->format('Y-01-01'),
            default  => null,
        };
    }
}
