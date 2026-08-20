<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Command\AddPrescriptionLine;

use App\Context\Consultation\Application\Port\CatalogItemProviderInterface;
use App\Context\Consultation\Domain\Repository\ConsultationRepositoryInterface;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use App\Context\Consultation\Domain\ValueObject\UserId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AddPrescriptionLineHandler
{
    public function __construct(
        private ConsultationRepositoryInterface $consultations,
        private CatalogItemProviderInterface $catalogItems,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(AddPrescriptionLine $command): void
    {
        $consultation = $this->consultations->findById(ConsultationId::fromString($command->consultationId));

        if (null === $consultation || $consultation->getClinicId()->toString() !== $command->clinicId) {
            throw new \DomainException('Consultation not found');
        }

        $article = $this->catalogItems->detail('ARTICLE', $command->articleId, $command->clinicId);

        // An archived article may still be referenced by a stale client tab.
        if (null === $article || 'ACTIVE' !== $article->status) {
            throw new \DomainException('Catalog article not found');
        }

        $price = $this->catalogItems->resolvePrice($article, $command->clinicId);

        $consultation->addPrescriptionLine(
            $command->articleId,
            $article->code,
            $article->name,
            $command->dose,
            $command->frequency,
            $command->durationDays,
            $command->route,
            $command->quantity,
            $price->minorUnits,
            $price->currency,
            $price->taxCategoryCode,
            UserId::fromString($command->createdByUserId),
            $this->clock->now(),
        );

        $this->consultations->save($consultation);
    }
}
