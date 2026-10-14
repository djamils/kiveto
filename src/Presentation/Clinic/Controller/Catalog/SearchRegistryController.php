<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Catalog;

use App\Shared\Application\Bus\QueryBusInterface;
use App\System\PharmaceuticalRegistry\Application\Query\SearchMarketingAuthorizations\MarketingAuthorizationSearchResult;
use App\System\PharmaceuticalRegistry\Application\Query\SearchMarketingAuthorizations\SearchMarketingAuthorizations;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Rechercher dans le registre » on a new drug's sheet: the marketed
 * authorisations whose name, or barcode, matches what is typed.
 */
#[Route(path: '/catalog/registry', name: 'clinic_catalogue_registry_search', methods: ['GET'])]
final readonly class SearchRegistryController
{
    private const int LIMIT = 30;

    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $term    = mb_trim($request->query->getString('q'));
        $results = $this->queryBus->ask(new SearchMarketingAuthorizations(term: $term, limit: self::LIMIT));
        $out     = [];

        foreach (\is_array($results) ? $results : [] as $result) {
            if (!$result instanceof MarketingAuthorizationSearchResult || 'WITHDRAWN' === mb_strtoupper($result->status)) {
                continue;
            }

            $out[] = [
                'id'   => $result->id,
                'lib'  => $result->commercialName,
                'labo' => $result->holderLaboratory,
                'gtin' => $result->gtin ?? '',
            ];
        }

        return new JsonResponse($out);
    }
}
