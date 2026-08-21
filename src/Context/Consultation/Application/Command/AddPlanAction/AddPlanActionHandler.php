<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Command\AddPlanAction;

use App\Context\Consultation\Application\Port\CatalogItemProviderInterface;
use App\Context\Consultation\Domain\Repository\ConsultationRepositoryInterface;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use App\Context\Consultation\Domain\ValueObject\PlanActionKind;
use App\Context\Consultation\Domain\ValueObject\UserId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AddPlanActionHandler
{
    public function __construct(
        private ConsultationRepositoryInterface $consultations,
        private CatalogItemProviderInterface $catalogItems,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(AddPlanAction $command): void
    {
        $consultation = $this->consultations->findById(ConsultationId::fromString($command->consultationId));

        if (null === $consultation || $consultation->getClinicId()->toString() !== $command->clinicId) {
            throw new \DomainException('Consultation not found');
        }

        $kind = PlanActionKind::tryFrom($command->kind);

        if (null === $kind) {
            throw new \InvalidArgumentException('Unknown plan action kind');
        }

        $unitPriceMinorUnits = null;
        $currency            = null;
        $taxCategoryCode     = null;
        $catalogCode         = $command->catalogCode;

        // Only catalog-backed acts are billable, and their price is snapshotted here.
        if ($kind->isBillable() && null !== $command->catalogItemId) {
            $act = $this->catalogItems->detail('ACT', $command->catalogItemId, $command->clinicId);

            // An archived act may still be referenced by a stale client tab.
            if (null === $act || 'ACTIVE' !== $act->status) {
                throw new \DomainException('Catalog act not found');
            }

            $price = $this->catalogItems->resolvePrice($act, $command->clinicId);

            // The catalog is authoritative: a client-supplied code must never
            // end up next to a price resolved for a different item.
            $catalogCode         = $act->code;
            $unitPriceMinorUnits = $price->minorUnits;
            $currency            = $price->currency;
            $taxCategoryCode     = $price->taxCategoryCode;
        }

        $consultation->addPlanAction(
            $kind,
            $command->description,
            $catalogCode,
            $command->posology,
            $command->durationDays,
            $command->followUpDays,
            $command->quantity,
            $unitPriceMinorUnits,
            $currency,
            $taxCategoryCode,
            UserId::fromString($command->createdByUserId),
            $this->clock->now(),
        );

        $this->consultations->save($consultation);
    }
}
