<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Query\GetMarketingAuthorizationDetail;

use App\System\PharmaceuticalRegistry\Application\Port\PharmaceuticalRefReadRepositoryInterface;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\MarketingAuthorizationId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetMarketingAuthorizationDetailHandler
{
    public function __construct(
        private PharmaceuticalRefReadRepositoryInterface $readRepository,
    ) {
    }

    public function __invoke(GetMarketingAuthorizationDetail $query): ?MarketingAuthorizationDetailView
    {
        $view = $this->readRepository->findById(MarketingAuthorizationId::fromString($query->marketingAuthorizationId));

        if (null === $view) {
            return null;
        }

        return new MarketingAuthorizationDetailView(
            id: $view->id,
            commercialName: $view->commercialName,
            holderLaboratory: $view->holderLaboratory,
            status: $view->status,
            authorizationDate: '',
            nature: '',
            pharmaceuticalForm: $view->pharmaceuticalForm,
            atcVetCode: $view->atcVetCode,
            permanentIdentifier: $view->permanentIdentifier,
            controlledSubstanceClass: $view->controlledSubstanceClass,
            presentations: $view->presentations,
            activeSubstances: $view->activeSubstanceLabels,
            targetUsages: [],
            jurisdictionalIdentifiers: [],
            lastImportSource: $view->lastImportSource,
            lastImportedAt: $view->lastImportedAt,
            createdAt: $view->lastImportedAt,
            updatedAt: $view->lastImportedAt,
        );
    }
}
