<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Catalog;

use App\Context\Catalog\Application\Command\Article\RestoreArticle\RestoreArticle;
use App\Presentation\Clinic\ViewModel\Catalog\CatalogErrorMessage;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Restoring a drug whose marketing authorization was withdrawn is refused by the
 * domain — the message says so rather than silently putting it back on sale.
 */
#[Route(path: '/catalogue/articles/{articleId}/restore', name: 'clinic_catalogue_article_restore', methods: ['POST'])]
final class RestoreArticleController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(string $articleId): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        try {
            $this->commandBus->dispatch(new RestoreArticle($articleId, $currentClinicId->toString()));
        } catch (\LogicException $exception) {
            $this->addFlash('error', CatalogErrorMessage::of($exception));

            return $this->redirectToRoute('clinic_catalogue_articles', ['status' => 'ARCHIVED']);
        }

        $this->addFlash('success', 'Article remis au catalogue.');

        return $this->redirectToRoute('clinic_catalogue_articles', ['status' => 'ARCHIVED']);
    }
}
