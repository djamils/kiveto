<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Catalog;

use App\Context\Catalog\Application\Command\Article\CreateDrugArticle\CreateDrugArticle;
use App\Context\Catalog\Application\Command\Article\CreateNonDrugArticle\CreateNonDrugArticle;
use App\Presentation\Clinic\Form\Shared\AmountParser;
use App\Presentation\Clinic\ViewModel\Catalog\CatalogErrorMessage;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A drug and a consumable are created by two different commands — the domain
 * refuses to make a drug through the ordinary door — so the kind of the form
 * decides which one is dispatched.
 */
#[Route(path: '/catalog/articles', name: 'clinic_catalogue_article_create', methods: ['POST'])]
final class CreateArticleController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $price = AmountParser::toMinorUnits($request->request->getString('base_price'));

        if (null === $price) {
            $this->addFlash('error', 'Le tarif saisi est invalide.');

            return $this->redirectToRoute('clinic_catalogue_articles');
        }

        $clinicId = $currentClinicId->toString();
        $kind     = $request->request->getString('kind');
        $name     = mb_trim($request->request->getString('name'));
        $code     = mb_strtoupper(mb_trim($request->request->getString('code')));
        $gtin     = mb_trim($request->request->getString('gtin'));
        $unit     = mb_trim($request->request->getString('unit_of_measure'));

        try {
            if ('DRUG' === $kind) {
                $prescriptionClass = $request->request->getString('prescription_class', 'NONE');

                $this->commandBus->dispatch(new CreateDrugArticle(
                    clinicId: $clinicId,
                    name: $name,
                    code: $code,
                    gtin: '' !== $gtin ? $gtin : null,
                    taxCategoryCode: $request->request->getString('tax_category'),
                    basePriceMinorUnits: $price,
                    basePriceCurrency: 'EUR',
                    unitOfMeasure: '' !== $unit ? $unit : 'UNIT',
                    // No marketing authorization is looked up from here: the
                    // registry reference is picked in the pharmacy screens.
                    authorizationRef: null,
                    requiresPrescription: 'NONE' !== $prescriptionClass,
                    prescriptionClass: $prescriptionClass,
                    isControlledSubstance: \in_array($prescriptionClass, ['RX_CONTROLLED', 'RX_NARCOTIC'], true),
                    trackStock: $request->request->getBoolean('track_stock'),
                ));
            } else {
                $this->commandBus->dispatch(new CreateNonDrugArticle(
                    clinicId: $clinicId,
                    name: $name,
                    code: $code,
                    kind: $kind,
                    gtin: '' !== $gtin ? $gtin : null,
                    taxCategoryCode: $request->request->getString('tax_category'),
                    basePriceMinorUnits: $price,
                    basePriceCurrency: 'EUR',
                    unitOfMeasure: '' !== $unit ? $unit : 'UNIT',
                    trackStock: $request->request->getBoolean('track_stock'),
                ));
            }
        } catch (\ValueError $exception) {
            return $this->refuse($exception);
        } catch (\LogicException $exception) {
            return $this->refuse($exception);
        }

        $this->addFlash('success', 'Article ajouté au catalogue.');

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
