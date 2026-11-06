<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Account;

use App\Presentation\Backoffice\Audit\ConsoleAuditTrail;
use App\Presentation\Backoffice\Security\BackofficeAuthenticator;
use App\Presentation\Backoffice\Security\CurrentAdmin;
use App\Presentation\Backoffice\ViewModel\QrCode;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\Audit\Domain\ValueObject\AuditCategory;
use App\System\IdentityAccess\Application\Command\EnableSecondFactor\EnableSecondFactor;
use App\System\IdentityAccess\Application\Query\PrepareSecondFactor\PrepareSecondFactor;
use App\System\IdentityAccess\Application\Query\PrepareSecondFactor\SecondFactorEnrollment;
use App\System\IdentityAccess\Domain\SecondFactor\InvalidSecondFactorCodeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Application d'authentification · Reconfigurer »: a new key replaces the
 * old one once a code of it is typed; fresh backup codes come with it.
 */
final class SecondFactorSetupController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentAdmin $currentAdmin,
        private readonly ConsoleAuditTrail $auditTrail,
    ) {
    }

    #[Route(path: '/account/second-factor', name: 'backoffice_account_second_factor', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $admin   = $this->currentAdmin->get();
        $session = $request->getSession();
        $error   = null;

        $secret = $session->get(BackofficeAuthenticator::ENROLLMENT_SECRET);
        $setup  = \is_string($secret) ? new SecondFactorEnrollment($secret, $admin->email) : $this->queryBus->ask(new PrepareSecondFactor($admin->email));
        \assert($setup instanceof SecondFactorEnrollment);
        $session->set(BackofficeAuthenticator::ENROLLMENT_SECRET, $setup->secret);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid(BackofficeAuthenticator::CSRF_ID, $request->request->getString('_csrf_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }

            try {
                $codes = $this->commandBus->dispatch(new EnableSecondFactor($admin->id, $setup->secret, $request->request->getString('code')));
                BackofficeAuthenticator::keepFreshBackupCodes($session, $codes);
                $session->remove(BackofficeAuthenticator::ENROLLMENT_SECRET);
                $this->auditTrail->record('auth.2fa_enabled', AuditCategory::ACCESS, 'Double authentification reconfigurée', targetUserId: $admin->id);

                return $this->redirectToRoute('backoffice_backup_codes');
            } catch (InvalidSecondFactorCodeException $exception) {
                $error = $exception->getMessage();
            }
        }

        return $this->render('backoffice/console/account/second_factor.html.twig', [
            'email'  => $admin->email,
            'setup'  => $setup,
            'qr'     => QrCode::svg($setup->uri()),
            'error'  => $error,
            'action' => $this->generateUrl('backoffice_account_second_factor'),
        ], new Response(status: null !== $error ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
