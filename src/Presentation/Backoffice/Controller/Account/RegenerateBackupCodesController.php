<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Account;

use App\Presentation\Backoffice\Account\AccountProfileSaver;
use App\Presentation\Backoffice\Audit\ConsoleAuditTrail;
use App\Presentation\Backoffice\Security\BackofficeAuthenticator;
use App\Presentation\Backoffice\Security\CurrentAdmin;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Audit\Domain\ValueObject\AuditCategory;
use App\System\IdentityAccess\Application\Command\RegenerateBackupCodes\RegenerateBackupCodes;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Codes de secours · Régénérer »: the previous ten stop working, the new
 * ones are shown once.
 */
final class RegenerateBackupCodesController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentAdmin $currentAdmin,
        private readonly ConsoleAuditTrail $auditTrail,
    ) {
    }

    #[Route(path: '/account/backup-codes', name: 'backoffice_account_backup_codes_regenerate', methods: ['POST'])]
    public function __invoke(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(AccountProfileSaver::CSRF_ID, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $admin = $this->currentAdmin->get();

        BackofficeAuthenticator::keepFreshBackupCodes($request->getSession(), $this->commandBus->dispatch(new RegenerateBackupCodes($admin->id)));
        $this->auditTrail->record('auth.backup_codes_regenerated', AuditCategory::ACCESS, 'Codes de secours régénérés', targetUserId: $admin->id);

        return $this->redirectToRoute('backoffice_backup_codes');
    }
}
