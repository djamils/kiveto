<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\SubmitPetChangeRequest;

use App\Context\ClientPortal\Application\Port\AnimalWeightsInterface;
use App\Context\ClientPortal\Application\Port\ClientDirectoryInterface;
use App\Context\ClientPortal\Application\Port\ClinicProfileInterface;
use App\Context\ClientPortal\Application\Port\PortalLockInterface;
use App\Context\ClientPortal\Application\Port\PortalPet;
use App\Context\ClientPortal\Application\Port\PortalRequestBellInterface;
use App\Context\ClientPortal\Application\Port\PrescriberDirectoryInterface;
use App\Context\ClientPortal\Application\Service\PortalRequestPets;
use App\Context\ClientPortal\Domain\Exception\InvalidPortalRequestException;
use App\Context\ClientPortal\Domain\Exception\PortalRequestRateLimitedException;
use App\Context\ClientPortal\Domain\Request\ChangeField;
use App\Context\ClientPortal\Domain\Request\PetChangeRequest;
use App\Context\ClientPortal\Domain\Request\PetChangeRequestRepositoryInterface;
use App\Context\ClientPortal\Domain\Request\PortalRequestId;
use App\Context\ClientPortal\Domain\Request\ProposedChange;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Snapshots the pet's current value of the field, then rings the bell of
 * the `MANAGE_ANIMALS` holders (fixed labels, pet and client names only).
 */
#[AsMessageHandler]
final readonly class SubmitPetChangeRequestHandler
{
    public const int MAX_OPEN_PER_FIELD = 3;

    private const array FIELD_LABELS = [
        'name'          => 'Nom',
        'weight'        => 'Poids',
        'sterilization' => 'Stérilisation',
        'chip_number'   => 'Numéro d\'identification',
        'other'         => 'Autre demande',
    ];

    public function __construct(
        private PortalRequestPets $pets,
        private PetChangeRequestRepositoryInterface $requests,
        private AnimalWeightsInterface $weights,
        private ClinicProfileInterface $clinics,
        private ClientDirectoryInterface $clients,
        private PrescriberDirectoryInterface $staff,
        private PortalRequestBellInterface $bell,
        private UuidGeneratorInterface $uuids,
        private ClockInterface $clock,
        private PortalLockInterface $locks,
    ) {
    }

    public function __invoke(SubmitPetChangeRequest $command): string
    {
        $pet   = $this->pets->eligible($command->userId, $command->clinicId, $command->petId);
        $field = ChangeField::tryFrom($command->field) ?? throw new InvalidPortalRequestException('field', \sprintf('Champ inconnu : « %s ».', $command->field));
        $now   = $this->clock->now();
        $today = $now->setTimezone(new \DateTimeZone($this->clinics->clinicOf($command->clinicId)->timeZone));

        $change = ProposedChange::of($field, $command->proposedValue, $command->measuredOn, $command->note, $today);

        // Serialised per pet: two concurrent submits never both pass the cap
        $this->locks->lockPetChanges($command->clinicId, $pet->id);

        if ($this->requests->countSubmitted($command->clinicId, $pet->id, $field) >= self::MAX_OPEN_PER_FIELD) {
            throw new PortalRequestRateLimitedException(null);
        }

        $request = PetChangeRequest::submit(
            PortalRequestId::fromString($this->uuids->generate()),
            $command->clinicId,
            $pet->primaryOwnerClientId,
            $command->userId,
            $pet->id,
            $change,
            $this->currentValue($command->clinicId, $pet, $field),
            $now,
        );

        $this->requests->save($request);

        $client = $this->clients->contactOf($command->clinicId, $pet->primaryOwnerClientId);
        $this->bell->announce(
            $command->clinicId,
            $this->staff->animalManagers($command->clinicId),
            $request->id()->toString(),
            'Demande de modification — ' . $pet->name,
            null === $client ? self::FIELD_LABELS[$field->value] : $client->displayName . ' · ' . self::FIELD_LABELS[$field->value],
        );

        return $request->id()->toString();
    }

    /** The pet's value of the field when asked; the weight in grams. */
    private function currentValue(string $clinicId, PortalPet $pet, ChangeField $field): ?string
    {
        return match ($field) {
            ChangeField::NAME          => $pet->name,
            ChangeField::WEIGHT        => null === ($weight = $this->weights->latestOf($clinicId, $pet->id)) ? null : (string) $weight->weightGrams,
            ChangeField::STERILIZATION => $pet->reproductiveStatus,
            ChangeField::CHIP_NUMBER   => $pet->microchipNumber,
            ChangeField::OTHER         => null,
        };
    }
}
