<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Catalog;

use App\Context\Catalog\Application\Command\Article\CreateDrugArticle\CreateDrugArticle;
use App\Context\Catalog\Application\Command\Article\CreateNonDrugArticle\CreateNonDrugArticle;
use App\Presentation\Clinic\ViewModel\Catalog\CatalogEndpoint;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Ajouter au catalogue » on a new article's sheet. A drug and a consumable
 * are created by two different commands — the domain refuses to make a drug
 * through the ordinary door — so the kind decides which one is dispatched. A
 * drug picked from the pharmaceutical registry carries its authorisation.
 */
#[Route(path: '/catalog/articles', name: 'clinic_catalogue_article_create', methods: ['POST'])]
final readonly class CreateArticleController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private CatalogEndpoint $endpoint,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        if (null !== $refused = $this->endpoint->guard($request)) {
            return $refused;
        }

        $price = CatalogEndpoint::cents($request, 'price');

        if (null === $price) {
            return $this->endpoint->refuse('Le prix de vente saisi est invalide.');
        }

        $priceMode = CatalogEndpoint::priceMode($request);

        if (null === $priceMode) {
            return $this->endpoint->refuse(CatalogEndpoint::INVALID_PRICE_MODE);
        }

        $form     = $request->request;
        $clinicId = $this->endpoint->clinicId();
        $kind     = $form->getString('kind');
        $name     = mb_trim($form->getString('name'));
        $code     = mb_strtoupper(mb_trim($form->getString('code')));
        $gtin     = CatalogEndpoint::text($request, 'gtin');
        $unit     = CatalogEndpoint::text($request, 'unit') ?? 'UNIT';
        $tax      = $form->getString('tax_category');
        $stock    = $form->getBoolean('track_stock');

        try {
            if ('DRUG' === $kind) {
                $class = $form->getString('prescription_class', 'NONE');

                $id = $this->commandBus->dispatch(new CreateDrugArticle(
                    clinicId: $clinicId,
                    name: $name,
                    code: $code,
                    gtin: $gtin,
                    taxCategoryCode: $tax,
                    basePriceMinorUnits: $price,
                    basePriceCurrency: 'EUR',
                    basePriceMode: $priceMode,
                    unitOfMeasure: $unit,
                    authorizationRef: CatalogEndpoint::text($request, 'authorization_ref'),
                    requiresPrescription: 'NONE' !== $class,
                    prescriptionClass: $class,
                    isControlledSubstance: \in_array($class, ['RX_CONTROLLED', 'RX_NARCOTIC'], true),
                    trackStock: $stock,
                ));
            } else {
                $id = $this->commandBus->dispatch(new CreateNonDrugArticle(
                    clinicId: $clinicId,
                    name: $name,
                    code: $code,
                    kind: $kind,
                    gtin: $gtin,
                    taxCategoryCode: $tax,
                    basePriceMinorUnits: $price,
                    basePriceCurrency: 'EUR',
                    basePriceMode: $priceMode,
                    unitOfMeasure: $unit,
                    trackStock: $stock,
                ));
            }
        } catch (\ValueError $exception) {
            return $this->endpoint->refuse($exception);
        } catch (\LogicException $exception) {
            return $this->endpoint->refuse($exception);
        }

        return $this->endpoint->ok(['id' => $id]);
    }
}
