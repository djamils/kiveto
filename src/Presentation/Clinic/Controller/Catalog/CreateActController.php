<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Catalog;

use App\Context\Catalog\Application\Command\Act\CreateAct\CreateAct;
use App\Presentation\Clinic\Form\Shared\AmountParser;
use App\Presentation\Clinic\ViewModel\Catalog\CatalogErrorMessage;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/catalog/acts', name: 'clinic_catalogue_act_create', methods: ['POST'])]
final class CreateActController extends AbstractController
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

            return $this->redirectToRoute('clinic_catalogue');
        }

        $description = mb_trim($request->request->getString('description'));

        try {
            $this->commandBus->dispatch(new CreateAct(
                clinicId: $currentClinicId->toString(),
                name: mb_trim($request->request->getString('name')),
                code: mb_strtoupper(mb_trim($request->request->getString('code'))),
                description: '' !== $description ? $description : null,
                category: $request->request->getString('category'),
                taxCategoryCode: $request->request->getString('tax_category'),
                basePriceMinorUnits: $price,
                basePriceCurrency: 'EUR',
                estimatedDurationMinutes: $request->request->getInt('duration', 20),
                requiresAnesthesia: $request->request->getBoolean('requires_anesthesia'),
            ));
        } catch (\ValueError $exception) {
            return $this->refuse($exception);
        } catch (\LogicException $exception) {
            return $this->refuse($exception);
        }

        $this->addFlash('success', 'Acte ajouté au catalogue.');

        return $this->redirectToRoute('clinic_catalogue');
    }

    /**
     * The domain refused the change, or the form carried a value no select
     * offers. Either way the counter is told in French and stays where it was.
     */
    private function refuse(\Throwable $exception): Response
    {
        $this->addFlash('error', CatalogErrorMessage::of($exception));

        return $this->redirectToRoute('clinic_catalogue');
    }
}
