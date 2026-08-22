<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Catalog;

use App\Context\Catalog\Application\Command\Article\ChangeArticleBasePrice\ChangeArticleBasePrice;
use App\Context\Catalog\Application\Command\Article\ChangeArticleTaxCategory\ChangeArticleTaxCategory;
use App\Context\Catalog\Application\Command\Article\RenameArticle\RenameArticle;
use App\Context\Catalog\Application\Command\Article\UpdateArticlePrescriptionFlags\UpdateArticlePrescriptionFlags;
use App\Context\Catalog\Application\Query\Article\GetArticleDetail\ArticleDetailView;
use App\Context\Catalog\Application\Query\Article\GetArticleDetail\GetArticleDetail;
use App\Presentation\Clinic\Form\Shared\AmountParser;
use App\Presentation\Clinic\ViewModel\Catalog\CatalogErrorMessage;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/catalog/articles/{articleId}', name: 'clinic_catalogue_article_update', methods: ['POST'])]
final class UpdateArticleController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(Request $request, string $articleId): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $clinicId = $currentClinicId->toString();

        try {
            $article = $this->queryBus->ask(new GetArticleDetail($articleId, $clinicId));
        } catch (\DomainException) {
            throw $this->createNotFoundException('Article introuvable.');
        }

        \assert($article instanceof ArticleDetailView);

        $name  = mb_trim($request->request->getString('name'));
        $price = AmountParser::toMinorUnits($request->request->getString('base_price'));
        $tax   = $request->request->getString('tax_category');

        if (null === $price) {
            $this->addFlash('error', 'Le tarif saisi est invalide.');

            return $this->redirectToRoute('clinic_catalogue_articles');
        }

        try {
            if ('' !== $name && $name !== $article->name) {
                $this->commandBus->dispatch(new RenameArticle($articleId, $clinicId, $name));
            }

            if ($price !== $article->basePriceMinorUnits) {
                $this->commandBus->dispatch(new ChangeArticleBasePrice(
                    $articleId,
                    $clinicId,
                    $price,
                    $article->basePriceCurrency,
                ));
            }

            if ('' !== $tax && $tax !== $article->taxCategoryCode) {
                $this->commandBus->dispatch(new ChangeArticleTaxCategory($articleId, $clinicId, $tax));
            }

            // Only a drug carries prescription flags; the domain refuses them
            // on anything else.
            if ('DRUG' === $article->kind && $request->request->has('prescription_class')) {
                $class = $request->request->getString('prescription_class', 'NONE');

                $this->commandBus->dispatch(new UpdateArticlePrescriptionFlags(
                    articleId: $articleId,
                    clinicId: $clinicId,
                    requiresPrescription: 'NONE' !== $class,
                    prescriptionClass: $class,
                    isControlledSubstance: \in_array($class, ['RX_CONTROLLED', 'RX_NARCOTIC'], true),
                ));
            }
        } catch (\ValueError $exception) {
            return $this->refuse($exception);
        } catch (\LogicException $exception) {
            return $this->refuse($exception);
        }

        $this->addFlash('success', 'Article mis à jour.');

        return $this->redirectToRoute('clinic_catalogue_articles');
    }

    /**
     * The domain refused the change, or the form carried a value no select
     * offers. Either way the counter is told in French and stays where it was.
     */
    private function refuse(\Throwable $exception): Response
    {
        $this->addFlash('error', CatalogErrorMessage::of($exception));

        return $this->redirectToRoute('clinic_catalogue_articles');
    }
}
