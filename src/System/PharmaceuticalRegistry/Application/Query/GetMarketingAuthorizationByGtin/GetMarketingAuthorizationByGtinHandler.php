<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Query\GetMarketingAuthorizationByGtin;

use App\System\PharmaceuticalRegistry\Application\Port\PharmaceuticalRefReadRepositoryInterface;
use App\System\PharmaceuticalRegistry\Application\Query\GetMarketingAuthorizationDetail\MarketingAuthorizationDetailView;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\Gtin;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetMarketingAuthorizationByGtinHandler
{
    public function __construct(
        private PharmaceuticalRefReadRepositoryInterface $readRepository,
    ) {
    }

    public function __invoke(GetMarketingAuthorizationByGtin $query): ?MarketingAuthorizationDetailView
    {
        $view = $this->readRepository->findByGtin(Gtin::fromString($query->gtin));

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
            controlledSubstanceClass: null,
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
