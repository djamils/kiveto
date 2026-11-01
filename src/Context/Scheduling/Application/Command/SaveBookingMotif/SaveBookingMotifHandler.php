<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\SaveBookingMotif;

use App\Context\Scheduling\Application\Port\MembershipEligibilityCheckerInterface;
use App\Context\Scheduling\Domain\Booking\BookingMotif;
use App\Context\Scheduling\Domain\Booking\BookingMotifId;
use App\Context\Scheduling\Domain\Booking\BookingMotifNotFoundException;
use App\Context\Scheduling\Domain\Booking\BookingMotifRepositoryInterface;
use App\Context\Scheduling\Domain\Booking\BookingPreventiveCareKind;
use App\Context\Scheduling\Domain\Booking\ConfirmationMode;
use App\Context\Scheduling\Domain\Booking\InvalidBookingConfigurationException;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SaveBookingMotifHandler
{
    /** Same roles as the appointments a practitioner can take. */
    private const array PRACTITIONER_ROLES = ['VETERINARY', 'VETERINARY_ASSISTANT'];

    public function __construct(
        private BookingMotifRepositoryInterface $motifs,
        private MembershipEligibilityCheckerInterface $eligibility,
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SaveBookingMotif $command): string
    {
        $clinicId     = ClinicId::fromString($command->clinicId);
        $confirmation = ConfirmationMode::tryFrom($command->confirmationKnownClient)
            ?? throw new InvalidBookingConfigurationException('confirmationKnownClient', 'Mode de confirmation inconnu.');
        $preventiveCareKind = $this->preventiveCareKind($command->preventiveCareKind);
        $blockTypes         = $this->blockTypes($command->allowedBlockTypes);
        $practitionerIds    = $this->eligiblePractitioners($clinicId, $command->practitionerIds);

        if (null === $command->motifId) {
            $motif = BookingMotif::create(
                BookingMotifId::fromString($this->uuidGenerator->generate()),
                $clinicId,
                $command->label,
                $command->description,
                $command->durationMin,
                $command->onlineEnabled,
                $confirmation,
                $blockTypes,
                $practitionerIds,
                $preventiveCareKind,
                $command->newClientsAllowed,
                $command->sortOrder,
            );
        } else {
            $motif = $this->motifs->findInClinic(BookingMotifId::fromString($command->motifId), $clinicId)
                ?? throw new BookingMotifNotFoundException($command->motifId);
            $motif->update(
                $command->label,
                $command->description,
                $command->durationMin,
                $command->onlineEnabled,
                $confirmation,
                $blockTypes,
                $practitionerIds,
                $preventiveCareKind,
                $command->newClientsAllowed,
                $command->sortOrder,
            );
        }

        $this->motifs->save($motif);

        return $motif->id()->toString();
    }

    private function preventiveCareKind(?string $value): ?BookingPreventiveCareKind
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return BookingPreventiveCareKind::tryFrom($value)
            ?? throw new InvalidBookingConfigurationException('preventiveCareKind', 'Type de soin préventif inconnu.');
    }

    /**
     * @param list<string> $values
     *
     * @return list<PlanningBlockType>
     */
    private function blockTypes(array $values): array
    {
        return array_map(
            static fn (string $value): PlanningBlockType => PlanningBlockType::tryFrom($value)
                ?? throw new InvalidBookingConfigurationException('allowedBlockTypes', 'Type de plage inconnu.'),
            $values,
        );
    }

    /**
     * @param list<string> $values
     *
     * @return list<UserId>
     */
    private function eligiblePractitioners(ClinicId $clinicId, array $values): array
    {
        $now = $this->clock->now();

        return array_map(
            function (string $value) use ($clinicId, $now): UserId {
                $userId = UserId::fromString($value);

                if (!$this->eligibility->isUserEligibleForClinicAt($userId, $clinicId, $now, self::PRACTITIONER_ROLES)) {
                    throw new InvalidBookingConfigurationException('practitionerIds', 'Ce praticien ne peut pas prendre de rendez-vous dans cette clinique.');
                }

                return $userId;
            },
            $values,
        );
    }
}
