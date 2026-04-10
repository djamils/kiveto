<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Command\StartConsultationFromWaitingRoomEntry;

use App\Context\Consultation\Application\Port\PractitionerEligibilityCheckerInterface;
use App\Context\Consultation\Application\Port\SchedulingServiceCoordinatorInterface;
use App\Context\Consultation\Domain\Consultation;
use App\Context\Consultation\Domain\Repository\ConsultationRepositoryInterface;
use App\Context\Consultation\Domain\ValueObject\AnimalId;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use App\Context\Consultation\Domain\ValueObject\OwnerId;
use App\Context\Consultation\Domain\ValueObject\UserId;
use App\Context\Consultation\Domain\ValueObject\WaitingRoomEntryId;
use App\Context\Scheduling\Application\Query\GetWaitingRoomEntryDetails\GetWaitingRoomEntryDetails;
use App\Context\Scheduling\Application\Query\GetWaitingRoomEntryDetails\WaitingRoomEntryDetailsDTO;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class StartConsultationFromWaitingRoomEntryHandler
{
    public function __construct(
        private ConsultationRepositoryInterface $consultations,
        private PractitionerEligibilityCheckerInterface $eligibilityChecker,
        private SchedulingServiceCoordinatorInterface $schedulingCoordinator,
        private QueryBusInterface $queryBus,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(StartConsultationFromWaitingRoomEntry $command): string
    {
        $waitingRoomEntryId = WaitingRoomEntryId::fromString($command->waitingRoomEntryId);
        $startedByUserId    = UserId::fromString($command->startedByUserId);
        $now                = $this->clock->now();

        // 1. Get waiting room entry details from Scheduling BC (via query)
        $entryDetails = $this->queryBus->ask(
            new GetWaitingRoomEntryDetails(
                $command->waitingRoomEntryId,
            )
        );
        \assert($entryDetails instanceof WaitingRoomEntryDetailsDTO);

        $clinicId = ClinicId::fromString($entryDetails->clinicId);

        // 2. Check eligibility (VETERINARY role required)
        $isEligible = $this->eligibilityChecker->isEligibleForClinicAt(
            $startedByUserId,
            $clinicId,
            $now,
            ['VETERINARY'],
        );
        if (!$isEligible) {
            throw new \DomainException('User is not eligible as practitioner for this clinic');
        }

        // 3. Ensure waiting room entry is in service
        $this->schedulingCoordinator->ensureWaitingRoomEntryInService($waitingRoomEntryId, $startedByUserId);

        // 4. Create consultation
        $consultationId = ConsultationId::generate();
        $ownerId        = $entryDetails->ownerId ? OwnerId::fromString($entryDetails->ownerId) : null;
        $animalId       = $entryDetails->animalId ? AnimalId::fromString($entryDetails->animalId) : null;

        $consultation = Consultation::startFromWaitingRoomEntry(
            $consultationId,
            $clinicId,
            $waitingRoomEntryId,
            $startedByUserId,
            $ownerId,
            $animalId,
            $now,
        );

        // 5. Persist
        $this->consultations->save($consultation);

        return $consultationId->toString();
    }
}
