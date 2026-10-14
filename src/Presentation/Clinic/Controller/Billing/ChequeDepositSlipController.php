<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Billing;

use App\Context\Billing\Application\Query\GetChequeDeposit\ChequeDepositView;
use App\Context\Billing\Application\Query\GetChequeDeposit\GetChequeDeposit;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Presentation\Clinic\ViewModel\Document\DocumentPdfFactory;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * The remittance slip (« bordereau de remise ») of a cheque deposit, as PDF.
 */
#[Route(
    path: '/billing/cheque-deposits/{depositId}/slip.pdf',
    name: 'clinic_cheque_deposit_slip',
    requirements: ['depositId' => Requirement::UUID],
    methods: ['GET'],
)]
final class ChequeDepositSlipController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly DocumentPdfFactory $documents,
    ) {
    }

    public function __invoke(string $depositId): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        try {
            $deposit = $this->queryBus->ask(new GetChequeDeposit($currentClinicId->toString(), $depositId));
        } catch (\DomainException) {
            throw $this->createNotFoundException('Remise introuvable.');
        }

        \assert($deposit instanceof ChequeDepositView);

        $clinic = $this->queryBus->ask(new GetClinic($currentClinicId->toString()));
        $clinic = $clinic instanceof ClinicDto ? $clinic : null;

        $pdf = $this->documents->render(
            'clinic/document/cheque_deposit.html.twig',
            ['deposit' => $deposit],
            $clinic,
            'Bordereau de remise de chèques',
            \sprintf('%d chèque%s', \count($deposit->cheques), \count($deposit->cheques) > 1 ? 's' : ''),
        );

        return $this->documents->response($pdf, 'remise-cheques-' . $deposit->depositedAt->format('Y-m-d'));
    }
}
