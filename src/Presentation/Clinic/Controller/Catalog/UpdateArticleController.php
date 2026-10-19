<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Catalog;

use App\Context\Catalog\Application\Command\Article\ChangeArticleBasePrice\ChangeArticleBasePrice;
use App\Context\Catalog\Application\Command\Article\ChangeArticleTaxCategory\ChangeArticleTaxCategory;
use App\Context\Catalog\Application\Command\Article\RenameArticle\RenameArticle;
use App\Context\Catalog\Application\Command\Article\ReviseArticleDetails\ReviseArticleDetails;
use App\Context\Catalog\Application\Command\Article\UpdateArticlePrescriptionFlags\UpdateArticlePrescriptionFlags;
use App\Context\Catalog\Application\Query\Article\GetArticleDetail\ArticleDetailView;
use App\Context\Catalog\Application\Query\Article\GetArticleDetail\GetArticleDetail;
use App\Presentation\Clinic\ViewModel\Catalog\CatalogEndpoint;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Enregistrer » on an article's sheet, and the bulk bar's price revision and
 * family change. Every field is optional; only what was sent and moved is
 * dispatched, one command per thing the domain changes.
 */
#[Route(path: '/catalog/articles/{articleId}', name: 'clinic_catalogue_article_update', methods: ['POST'])]
final readonly class UpdateArticleController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private QueryBusInterface $queryBus,
        private CatalogEndpoint $endpoint,
    ) {
    }

    public function __invoke(Request $request, string $articleId): JsonResponse
    {
        if (null !== $refused = $this->endpoint->guard($request)) {
            return $refused;
        }

        $clinicId = $this->endpoint->clinicId();
        $form     = $request->request;

        try {
            $article = $this->queryBus->ask(new GetArticleDetail($articleId, $clinicId));
            \assert($article instanceof ArticleDetailView);

            $name = CatalogEndpoint::text($request, 'name');

            if (null !== $name && $name !== $article->name) {
                $this->commandBus->dispatch(new RenameArticle($articleId, $clinicId, $name));
            }

            if ($form->has('price')) {
                $price     = CatalogEndpoint::cents($request, 'price');
                $priceMode = CatalogEndpoint::priceMode($request);

                if (null === $price) {
                    return $this->endpoint->refuse('Le prix de vente saisi est invalide.');
                }

                if (null === $priceMode) {
                    return $this->endpoint->refuse(CatalogEndpoint::INVALID_PRICE_MODE);
                }

                if ($price !== $article->basePriceMinorUnits || $priceMode !== $article->basePriceMode) {
                    $this->commandBus->dispatch(new ChangeArticleBasePrice($articleId, $clinicId, $price, $article->basePriceCurrency, $priceMode));
                }
            }

            $tax = CatalogEndpoint::text($request, 'tax_category');

            if (null !== $tax && $tax !== $article->taxCategoryCode) {
                $this->commandBus->dispatch(new ChangeArticleTaxCategory($articleId, $clinicId, $tax));
            }

            // Only a drug carries delivery rules; the domain refuses them on
            // anything else.
            if ('DRUG' === $article->kind && $form->has('prescription_class')) {
                $class = $form->getString('prescription_class', 'NONE');

                $this->commandBus->dispatch(new UpdateArticlePrescriptionFlags(
                    articleId: $articleId,
                    clinicId: $clinicId,
                    requiresPrescription: 'NONE' !== $class,
                    prescriptionClass: $class,
                    isControlledSubstance: \in_array($class, ['RX_CONTROLLED', 'RX_NARCOTIC'], true),
                ));
            }

            if ($form->has('unit')) {
                $this->commandBus->dispatch(new ReviseArticleDetails(
                    articleId: $articleId,
                    clinicId: $clinicId,
                    gtin: CatalogEndpoint::text($request, 'gtin'),
                    unitOfMeasure: CatalogEndpoint::text($request, 'unit') ?? $article->unitOfMeasure,
                    trackStock: $form->getBoolean('track_stock'),
                ));
            }
        } catch (\ValueError $exception) {
            return $this->endpoint->refuse($exception);
        } catch (\LogicException $exception) {
            return $this->endpoint->refuse($exception);
        }

        return $this->endpoint->ok();
    }
}
