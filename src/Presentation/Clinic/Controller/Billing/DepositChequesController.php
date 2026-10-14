<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Billing;

use App\Context\Billing\Application\Command\DepositPendingCheques\DepositPendingCheques;
use App\Context\Billing\Application\Query\GetChequeDeposit\ChequeDepositView;
use App\Context\Billing\Application\Query\GetChequeDeposit\GetChequeDeposit;
use App\Presentation\Clinic\ViewModel\Billing\BillingErrorMessage;
use App\Presentation\Clinic\ViewModel\Billing\BillingSheetBuilder;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * « Remise de chèques »: every cheque still in the drawer goes to the bank in
 * one remittance; the answer carries its slip, which the page opens.
 */
#[Route(path: '/billing/cheque-deposits', name: 'clinic_cheques_deposit', methods: ['POST'])]
final class DepositChequesController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('billing_cheque_deposit', $request->request->getString('_token')))) {
            return new JsonResponse(['error' => 'Token de sécurité invalide. Veuillez réessayer.'], JsonResponse::HTTP_FORBIDDEN);
        }

        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $clinicId = $currentClinicId->toString();
        $user     = $this->getUser();

        try {
            $depositId = $this->commandBus->dispatch(new DepositPendingCheques(
                clinicId: $clinicId,
                depositedByUserId: $user instanceof SecurityUser ? $user->id() : null,
            ));
        } catch (\DomainException $e) {
            return new JsonResponse(['error' => BillingErrorMessage::of($e)], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        \assert(\is_string($depositId));

        $deposit = $this->queryBus->ask(new GetChequeDeposit($clinicId, $depositId));
        \assert($deposit instanceof ChequeDepositView);

        $count = \count($deposit->cheques);

        return new JsonResponse([
            'message' => \sprintf(
                'Bordereau de remise prêt — %d chèque%s, %s',
                $count,
                $count > 1 ? 's' : '',
                BillingSheetBuilder::euros($deposit->totalMinorUnits),
            ),
            'slip' => $this->generateUrl('clinic_cheque_deposit_slip', ['depositId' => $depositId]),
        ]);
    }
}
