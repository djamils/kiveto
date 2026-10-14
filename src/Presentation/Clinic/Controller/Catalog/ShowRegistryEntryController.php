<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Catalog;

use App\Shared\Application\Bus\QueryBusInterface;
use App\System\PharmaceuticalRegistry\Application\Query\GetMarketingAuthorizationDetail\GetMarketingAuthorizationDetail;
use App\System\PharmaceuticalRegistry\Application\Query\GetMarketingAuthorizationDetail\MarketingAuthorizationDetailView;
use App\System\PharmaceuticalRegistry\Application\Query\GetWithdrawalPeriodsByMarketingAuthorization\GetWithdrawalPeriodsByMarketingAuthorization;
use App\System\PharmaceuticalRegistry\Application\Query\GetWithdrawalPeriodsByMarketingAuthorization\WithdrawalPeriodView;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * What the pharmaceutical registry says of one authorisation — the locked
 * fields of a linked drug's sheet (laboratory, form, active substances,
 * delivery class, withdrawal periods), and what a new drug picked from the
 * registry starts from.
 */
#[Route(path: '/catalog/registry/{authorizationId}', name: 'clinic_catalogue_registry_detail', methods: ['GET'])]
final readonly class ShowRegistryEntryController
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function __invoke(string $authorizationId): JsonResponse
    {
        try {
            $view = $this->queryBus->ask(new GetMarketingAuthorizationDetail($authorizationId));
        } catch (\Throwable) {
            $view = null;
        }

        if (!$view instanceof MarketingAuthorizationDetailView) {
            return new JsonResponse(['error' => 'Autorisation introuvable dans le registre.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $gtin = '';
        $rx   = false;

        foreach ($view->presentations as $presentation) {
            if (!\is_array($presentation)) {
                continue;
            }

            if ('' === $gtin && \is_string($presentation['gtin'] ?? null)) {
                $gtin = $presentation['gtin'];
            }

            $rx = $rx || true === ($presentation['prescriptionRequired'] ?? null);
        }

        return new JsonResponse([
            'id'          => $view->id,
            'lib'         => $view->commercialName,
            'labo'        => $view->holderLaboratory,
            'forme'       => $view->pharmaceuticalForm,
            'gtin'        => $gtin,
            'substances'  => array_values(array_filter($view->activeSubstances, \is_string(...))),
            'classe'      => null !== $view->controlledSubstanceClass ? 'RX_NARCOTIC' : ($rx ? 'RX' : 'NONE'),
            'marketed'    => 'WITHDRAWN' !== mb_strtoupper($view->status),
            'withdrawals' => $this->withdrawals($authorizationId),
        ]);
    }

    /**
     * @return list<array{species: string, days: int|null, purpose: string|null}>
     */
    private function withdrawals(string $authorizationId): array
    {
        try {
            $periods = $this->queryBus->ask(new GetWithdrawalPeriodsByMarketingAuthorization($authorizationId));
        } catch (\Throwable) {
            return [];
        }

        $out = [];

        foreach (\is_array($periods) ? $periods : [] as $period) {
            if ($period instanceof WithdrawalPeriodView) {
                $out[] = [
                    'species' => $period->targetSpeciesCode,
                    'days'    => $period->withdrawalPeriodValue,
                    'purpose' => $period->foodProductionPurpose,
                ];
            }
        }

        return $out;
    }
}
