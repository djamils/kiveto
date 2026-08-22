<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Catalog;

use App\Context\Catalog\Application\Command\Act\ChangeActBasePrice\ChangeActBasePrice;
use App\Context\Catalog\Application\Command\Act\ChangeActTaxCategory\ChangeActTaxCategory;
use App\Context\Catalog\Application\Command\Act\RenameAct\RenameAct;
use App\Context\Catalog\Application\Query\Act\GetActDetail\ActDetailView;
use App\Context\Catalog\Application\Query\Act\GetActDetail\GetActDetail;
use App\Presentation\Clinic\Form\Shared\AmountParser;
use App\Presentation\Clinic\ViewModel\Catalog\CatalogErrorMessage;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * One form, several commands: the domain changes a name, a price and a tax
 * category one at a time, so only what actually moved is dispatched.
 */
#[Route(path: '/catalogue/actes/{actId}', name: 'clinic_catalogue_act_update', methods: ['POST'])]
final class UpdateActController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(Request $request, string $actId): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $clinicId = $currentClinicId->toString();

        try {
            $act = $this->queryBus->ask(new GetActDetail($actId, $clinicId));
        } catch (\DomainException) {
            throw $this->createNotFoundException('Acte introuvable.');
        }

        \assert($act instanceof ActDetailView);

        $name  = mb_trim($request->request->getString('name'));
        $price = AmountParser::toMinorUnits($request->request->getString('base_price'));
        $tax   = $request->request->getString('tax_category');

        if (null === $price) {
            $this->addFlash('error', 'Le tarif saisi est invalide.');

            return $this->redirectToRoute('clinic_catalogue');
        }

        try {
            if ('' !== $name && $name !== $act->name) {
                $this->commandBus->dispatch(new RenameAct($actId, $clinicId, $name));
            }

            if ($price !== $act->basePriceMinorUnits) {
                $this->commandBus->dispatch(new ChangeActBasePrice($actId, $clinicId, $price, $act->basePriceCurrency));
            }

            if ('' !== $tax && $tax !== $act->taxCategoryCode) {
                $this->commandBus->dispatch(new ChangeActTaxCategory($actId, $clinicId, $tax));
            }
        } catch (\LogicException $exception) {
            $this->addFlash('error', CatalogErrorMessage::of($exception));

            return $this->redirectToRoute('clinic_catalogue');
        }

        $this->addFlash('success', 'Acte mis à jour.');

        return $this->redirectToRoute('clinic_catalogue');
    }
}
