<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Account;

use App\Presentation\Backoffice\Account\AccountProfileSaver;
use App\Presentation\Backoffice\Audit\ConsoleAuditTrail;
use App\Presentation\Backoffice\Security\BackofficeAuthenticator;
use App\Presentation\Backoffice\Security\CurrentAdmin;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Audit\Domain\ValueObject\AuditCategory;
use App\System\IdentityAccess\Application\Command\RevokeTrustedDevices\RevokeTrustedDevices;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Appareils de confiance · Tout révoquer »: every browser asks the code
 * again at its next sign-in.
 */
final class RevokeTrustedDevicesController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentAdmin $currentAdmin,
        private readonly ConsoleAuditTrail $auditTrail,
    ) {
    }

    #[Route(path: '/account/trusted-devices/revoke', name: 'backoffice_account_trusted_devices_revoke', methods: ['POST'])]
    public function __invoke(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(AccountProfileSaver::CSRF_ID, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $admin = $this->currentAdmin->get();
        $count = $this->commandBus->dispatch(new RevokeTrustedDevices($admin->id));

        $this->auditTrail->record(
            'auth.trusted_devices_revoked',
            AuditCategory::ACCESS,
            'Appareils de confiance révoqués',
            targetUserId: $admin->id,
            details: ['Appareils' => (string) (\is_int($count) ? $count : 0)],
        );
        $this->addFlash('success', 'Les appareils de confiance demanderont à nouveau le code.');

        $response = $this->redirectToRoute('backoffice_account');
        $response->headers->clearCookie(BackofficeAuthenticator::TRUST_COOKIE, '/');

        return $response;
    }
}
