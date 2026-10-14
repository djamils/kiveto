<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Catalog;

use App\Context\Catalog\Application\Command\Article\ArchiveArticle\ArchiveArticle;
use App\Presentation\Clinic\ViewModel\Catalog\CatalogEndpoint;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** « Archiver » an article — from its sheet or the bulk bar. */
#[Route(path: '/catalog/articles/{articleId}/archive', name: 'clinic_catalogue_article_archive', methods: ['POST'])]
final readonly class ArchiveArticleController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private CatalogEndpoint $endpoint,
    ) {
    }

    public function __invoke(Request $request, string $articleId): JsonResponse
    {
        if (null !== $refused = $this->endpoint->guard($request)) {
            return $refused;
        }

        try {
            $this->commandBus->dispatch(new ArchiveArticle($articleId, $this->endpoint->clinicId()));
        } catch (\LogicException $exception) {
            return $this->endpoint->refuse($exception);
        }

        return $this->endpoint->ok();
    }
}
