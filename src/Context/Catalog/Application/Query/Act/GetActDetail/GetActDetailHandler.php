<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Query\Act\GetActDetail;

use App\Context\Catalog\Domain\Act\Exception\ActNotFoundException;
use App\Context\Catalog\Domain\Act\Repository\ActRepositoryInterface;
use App\Context\Catalog\Domain\Act\ValueObject\ActId;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetActDetailHandler
{
    public function __construct(
        private readonly ActRepositoryInterface $actRepository,
    ) {
    }

    public function __invoke(GetActDetail $query): ActDetailView
    {
        $clinicId = ClinicId::fromString($query->clinicId);
        $act      = $this->actRepository->findById(ActId::fromString($query->actId), $clinicId);

        if (null === $act) {
            throw new ActNotFoundException($query->actId);
        }

        return new ActDetailView(
            id: $act->id()->toString(),
            clinicId: $act->clinicId()->toString(),
            name: $act->name()->toString(),
            code: $act->code()->toString(),
            description: $act->description()?->toString(),
            category: $act->category()->value,
            taxCategoryCode: $act->taxCategory()->toString(),
            basePriceMinorUnits: $act->basePrice()->minorUnits(),
            basePriceCurrency: $act->basePrice()->currency()->toString(),
            estimatedDurationMinutes: $act->estimatedDuration()->toMinutes(),
            requiresAnesthesia: $act->requiresAnesthesia(),
            status: $act->status()->value,
            createdAt: $act->createdAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $act->updatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
