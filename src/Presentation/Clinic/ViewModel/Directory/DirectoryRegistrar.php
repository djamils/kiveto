<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Directory;

use App\Context\Animal\Application\Query\FindByMicrochip\AnimalMicrochipView;
use App\Context\Animal\Application\Query\FindByMicrochip\FindByMicrochip;
use App\Context\Client\Application\Command\CreateClient\CreateClient;
use App\Context\Client\Application\Query\GetClientById\ClientView;
use App\Context\Client\Application\Query\GetClientById\GetClientById;
use App\Context\Client\Application\Query\ListClientIdsByPhone\ListClientIdsByPhone;
use App\Context\Client\Application\Query\ListCommunesForPostalCode\ListCommunesForPostalCode;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Localization\TimeZone;
use App\Shared\Domain\Time\ClockInterface;

/**
 * What the directory's two creation windows send, checked once more and
 * recorded: a client with the animals they come with, or an animal for a
 * client on file. Each answer is the message the directory shows next — a
 * success or the first thing to correct.
 */
final readonly class DirectoryRegistrar
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private QueryBusInterface $queryBus,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param array<array-key, mixed> $payload the window's state
     *
     * @return array{ok: bool, message: string, search: string}
     */
    public function createClient(string $clinicId, array $payload): array
    {
        $client = DirectoryClientInput::parse($payload);
        if (\is_string($client)) {
            return self::refused($client);
        }

        if (!$client->household && [] !== $this->phoneOwners($clinicId, $client)) {
            return self::refused('Téléphone déjà utilisé : vérifiez le doublon');
        }

        if ('' !== $client->street) {
            $communes = $this->queryBus->ask(new ListCommunesForPostalCode($client->postalCode));
            // When the directory of communes cannot be reached, the commune typed is kept.
            if (\is_array($communes) && [] === $communes) {
                return self::refused('Adresse : code postal invalide');
            }
            if (\is_array($communes) && !\in_array($client->city, $communes, true)) {
                return self::refused('Adresse : choisissez la commune');
            }
        }

        $animals = [];
        $chips   = [];
        $raws    = \is_array($payload['anis'] ?? null) ? $payload['anis'] : [];
        foreach ($raws as $raw) {
            if (!\is_array($raw) || DirectoryAnimalInput::isBlank($raw)) {
                continue;
            }
            $animal = $this->animal($clinicId, $raw);
            if (\is_string($animal)) {
                $name = \is_string($raw['nom'] ?? null) && '' !== mb_trim($raw['nom']) ? mb_trim($raw['nom']) : 'Animal';

                return self::refused($name . ' : ' . mb_lcfirst($animal));
            }
            if (null !== $animal->chip && isset($chips[$animal->chip])) {
                return self::refused($animal->name . ' : puce saisie deux fois');
            }
            $chips[(string) $animal->chip] = true;
            $animals[]                     = $animal;
        }

        try {
            $this->commandBus->dispatch(new CreateClient(
                clinicId: $clinicId,
                firstName: $client->firstName,
                lastName: $client->lastName,
                contactMethods: $client->contactMethods(),
                civility: $client->civility,
                postalAddress: $client->postalAddress(),
                preferredChannel: $client->preferredChannel(),
                channelsByConsentKind: $client->consents(),
                animals: array_map(static fn (DirectoryAnimalInput $animal) => $animal->toDto(), $animals),
                taxProfile: $client->taxProfile?->toClientDto(),
            ));
        } catch (\LogicException) {
            return self::refused('La fiche n\'a pas pu être créée : vérifiez les coordonnées et les animaux.');
        }

        $names = implode(', ', array_map(static fn (DirectoryAnimalInput $animal): string => $animal->name, $animals));

        return [
            'ok'      => true,
            'message' => 'Client créé — ' . $client->title() . ('' !== $names ? ' · ' . $names : ''),
            'search'  => $client->taxProfile->legalName ?? $client->lastName,
        ];
    }

    /**
     * @param array<array-key, mixed> $payload the window's state
     *
     * @return array{ok: bool, message: string, search: string}
     */
    public function createAnimal(string $clinicId, array $payload): array
    {
        $ownerId = \is_string($payload['cli'] ?? null) ? $payload['cli'] : '';
        $owner   = '' === $ownerId ? null : $this->queryBus->ask(new GetClientById($clinicId, $ownerId));

        if (!$owner instanceof ClientView || 'active' !== $owner->status) {
            return self::refused('Choisissez le propriétaire');
        }

        $animal = $this->animal($clinicId, \is_array($payload['a'] ?? null) ? $payload['a'] : []);
        if (\is_string($animal)) {
            return self::refused($animal);
        }

        try {
            $this->commandBus->dispatch($animal->toCreateAnimal($clinicId, $owner->id));
        } catch (\LogicException) {
            return self::refused('L\'animal n\'a pas pu être créé : vérifiez sa fiche.');
        }

        $title = ['madam' => 'Mme ', 'mister' => 'M. '][$owner->civility] ?? '';

        return [
            'ok'      => true,
            'message' => $animal->name . ' ajouté à ' . $title . $owner->fullName(),
            'search'  => $animal->name,
        ];
    }

    /**
     * The clients one of whose phones is one of the new client's.
     *
     * @return list<string>
     */
    public function phoneOwners(string $clinicId, DirectoryClientInput $client): array
    {
        $owners = [];
        foreach ($client->phones() as $phone) {
            $ids = $this->queryBus->ask(new ListClientIdsByPhone($clinicId, $phone));
            foreach (\is_array($ids) ? $ids : [] as $id) {
                if (\is_string($id) && !\in_array($id, $owners, true)) {
                    $owners[] = $id;
                }
            }
        }

        return $owners;
    }

    /** Today, in the clinic's time zone: a birth date cannot be later. */
    public function today(string $clinicId): \DateTimeImmutable
    {
        $clinic = $this->queryBus->ask(new GetClinic($clinicId));
        $now    = $this->clock->now();

        return $clinic instanceof ClinicDto ? $now->setTimezone(TimeZone::fromString($clinic->timeZone)->toNative()) : $now;
    }

    /**
     * @param array<array-key, mixed> $raw
     */
    private function animal(string $clinicId, array $raw): DirectoryAnimalInput|string
    {
        $animal = DirectoryAnimalInput::parse($raw, $this->today($clinicId));

        if (!$animal instanceof DirectoryAnimalInput || null === $animal->chip) {
            return $animal;
        }

        $known = $this->queryBus->ask(new FindByMicrochip($clinicId, $animal->chip));

        return $known instanceof AnimalMicrochipView ? 'Puce déjà enregistrée' : $animal;
    }

    /**
     * @return array{ok: bool, message: string, search: string}
     */
    private static function refused(string $message): array
    {
        return ['ok' => false, 'message' => $message, 'search' => ''];
    }
}
