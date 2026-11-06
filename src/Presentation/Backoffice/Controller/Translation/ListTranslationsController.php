<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Translation;

use App\Shared\Application\Bus\QueryBusInterface;
use App\System\Translation\Application\Query\SearchTranslations\SearchTranslations;
use App\System\Translation\Application\Query\SearchTranslations\TranslationSearchResult;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The translation catalogue of the apps, from the account menu.
 */
final class ListTranslationsController extends AbstractController
{
    public const int PER_PAGE = 50;

    public const array SCOPES = ['clinic', 'portal', 'backoffice', 'shared'];

    public function __construct(
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    #[Route(path: '/translations', name: 'backoffice_translations', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $query   = $request->query;
        $filters = [
            'scope'  => $query->getString('scope'),
            'locale' => $query->getString('locale'),
            'domain' => $query->getString('domain'),
            'key'    => $query->getString('key'),
            'value'  => $query->getString('value'),
        ];

        $result = $this->queryBus->ask(new SearchTranslations(
            scope: '' !== $filters['scope'] ? $filters['scope'] : null,
            locale: '' !== $filters['locale'] ? $filters['locale'] : null,
            domain: '' !== $filters['domain'] ? $filters['domain'] : null,
            keyContains: '' !== $filters['key'] ? $filters['key'] : null,
            valueContains: '' !== $filters['value'] ? $filters['value'] : null,
            updatedBy: null,
            updatedAfter: null,
            page: max(1, $query->getInt('page', 1)),
            perPage: self::PER_PAGE,
        ));
        \assert($result instanceof TranslationSearchResult);

        return $this->render('backoffice/console/translations/index.html.twig', [
            'result'    => $result,
            'filters'   => array_filter($filters, static fn (string $value): bool => '' !== $value),
            'scopes'    => self::SCOPES,
            'pageCount' => max(1, (int) ceil($result->total / self::PER_PAGE)),
        ]);
    }
}
