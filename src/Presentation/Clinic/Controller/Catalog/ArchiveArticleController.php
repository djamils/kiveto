<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Catalog;

use App\Context\Catalog\Application\Command\Article\ArchiveArticle\ArchiveArticle;
use App\Presentation\Clinic\ViewModel\Catalog\CatalogErrorMessage;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/catalog/articles/{articleId}/archive', name: 'clinic_catalogue_article_archive', methods: ['POST'])]
final class ArchiveArticleController extends AbstractController
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
            $this->commandBus->dispatch(new ArchiveArticle($articleId, $currentClinicId->toString()));
        } catch (\LogicException $exception) {
            $this->addFlash('error', CatalogErrorMessage::of($exception));

            return $this->redirectToRoute('clinic_catalogue_articles');
        }

        $this->addFlash('success', 'Article archivé. Il reste sur les documents déjà émis.');

        return $this->redirectToRoute('clinic_catalogue_articles');
    }
}
