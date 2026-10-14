<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Billing;

use App\Context\Billing\Application\Command\CloseCashDesk\CloseCashDesk;
use App\Presentation\Clinic\Form\Shared\AmountParser;
use App\Presentation\Clinic\ViewModel\Billing\BillingErrorMessage;
use App\Presentation\Clinic\ViewModel\Billing\BillingPageBuilder;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * « Clôturer la journée »: what was counted in the till (cash, cheques) and
 * the explanation of a difference. Closes today, in the clinic's time zone.
 */
#[Route(path: '/billing/cash-closing', name: 'clinic_cash_closing', methods: ['POST'])]
final class CloseCashDeskController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly BillingPageBuilder $pages,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('billing_cash_closing', $request->request->getString('_token')))) {
            return new JsonResponse(['error' => 'Token de sécurité invalide. Veuillez réessayer.'], JsonResponse::HTTP_FORBIDDEN);
        }

        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $clinicId = $currentClinicId->toString();
        $day      = $this->pages->businessDay($clinicId);
        $user     = $this->getUser();
        $cash     = mb_trim($request->request->getString('cash'));
        $cheques  = mb_trim($request->request->getString('cheques'));

        try {
            $number = $this->commandBus->dispatch(new CloseCashDesk(
                clinicId: $clinicId,
                businessDate: $day['date'],
                dayStart: $day['start'],
                dayEnd: $day['end'],
                countedCashMinorUnits: '' !== $cash ? AmountParser::toMinorUnits($cash) : null,
                countedChequesMinorUnits: '' !== $cheques ? AmountParser::toMinorUnits($cheques) : null,
                comment: mb_trim($request->request->getString('comment')),
                closedByUserId: $user instanceof SecurityUser ? $user->id() : null,
            ));
        } catch (\DomainException $e) {
            return new JsonResponse(['error' => BillingErrorMessage::of($e)], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        \assert(\is_int($number));

        return new JsonResponse([
            'number'  => $number,
            'message' => \sprintf(
                'Journée du %s clôturée — ticket Z n° %d scellé, encaissements du jour verrouillés',
                new \DateTimeImmutable($day['date'])->format('d/m/Y'),
                $number,
            ),
        ]);
    }
}
