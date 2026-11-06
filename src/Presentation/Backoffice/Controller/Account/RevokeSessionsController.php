<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Account;

use App\Presentation\Backoffice\Account\AccountProfileSaver;
use App\Presentation\Backoffice\Audit\ConsoleAuditTrail;
use App\Presentation\Backoffice\Security\CurrentAdmin;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Audit\Domain\ValueObject\AuditCategory;
use App\System\IdentityAccess\Application\Command\RevokeUserSessions\RevokeUserSessions;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * « Révoquer » one of the admin's sessions (`session_id`), or « Déconnecter
 * les autres sessions » (none given): this browser's own is kept.
 */
final class RevokeSessionsController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentAdmin $currentAdmin,
        private readonly ConsoleAuditTrail $auditTrail,
    ) {
    }

    #[Route(path: '/account/sessions/revoke', name: 'backoffice_account_sessions_revoke', methods: ['POST'])]
    public function __invoke(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(AccountProfileSaver::CSRF_ID, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $admin     = $this->currentAdmin->get();
        $sessionId = $request->request->getString('session_id');
        $single    = Uuid::isValid($sessionId);

        $count = $this->commandBus->dispatch(new RevokeUserSessions(
            userId: $admin->id,
            sessionRecordId: $single ? $sessionId : null,
            keepSessionId: $single ? null : $request->getSession()->getId(),
        ));

        $this->auditTrail->record(
            'auth.session_revoked',
            AuditCategory::ACCESS,
            $single ? 'Session révoquée' : 'Autres sessions déconnectées',
            targetUserId: $admin->id,
            details: ['Sessions' => (string) (\is_int($count) ? $count : 0)],
        );
        $this->addFlash('success', $single ? 'Session révoquée.' : 'Les autres sessions sont déconnectées.');

        return $this->redirectToRoute('backoffice_account');
    }
}
