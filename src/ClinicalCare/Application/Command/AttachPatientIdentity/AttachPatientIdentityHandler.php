<?php

declare(strict_types=1);

namespace App\ClinicalCare\Application\Command\AttachPatientIdentity;

use App\ClinicalCare\Application\Port\AnimalExistenceCheckerInterface;
use App\ClinicalCare\Application\Port\OwnerExistenceCheckerInterface;
use App\ClinicalCare\Domain\Repository\ConsultationRepositoryInterface;
use App\ClinicalCare\Domain\ValueObject\AnimalId;
use App\ClinicalCare\Domain\ValueObject\ConsultationId;
use App\ClinicalCare\Domain\ValueObject\OwnerId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AttachPatientIdentityHandler
{
    public function __construct(
        private ConsultationRepositoryInterface $consultations,
        private OwnerExistenceCheckerInterface $ownerChecker,
        private AnimalExistenceCheckerInterface $animalChecker,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(AttachPatientIdentity $command): void
    {
        $consultationId = ConsultationId::fromString($command->consultationId);
        $consultation = $this->consultations->findById($consultationId);

        if (null === $consultation) {
            throw new \DomainException('Consultation not found');
        }

        $ownerId = $command->ownerId ? OwnerId::fromString($command->ownerId) : null;
        $animalId = $command->animalId ? AnimalId::fromString($command->animalId) : null;

        // Validate existence
        if ($ownerId && !$this->ownerChecker->exists($ownerId)) {
            throw new \DomainException('Owner does not exist');
        }

        if ($animalId && !$this->animalChecker->exists($animalId)) {
            throw new \DomainException('Animal does not exist');
        }

        $consultation->attachPatientIdentity(
            $ownerId,
            $animalId,
            $this->clock->now(),
        );

        $this->consultations->save($consultation);
    }
}
