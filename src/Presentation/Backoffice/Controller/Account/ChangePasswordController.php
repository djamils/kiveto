<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Account;

use App\Presentation\Backoffice\Account\AccountProfileSaver;
use App\Presentation\Backoffice\Audit\ConsoleAuditTrail;
use App\Presentation\Backoffice\Security\CurrentAdmin;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Audit\Domain\ValueObject\AuditCategory;
use App\System\IdentityAccess\Application\Command\ChangeOwnPassword\ChangeOwnPassword;
use App\System\IdentityAccess\Application\Exception\UnacceptablePasswordException;
use App\System\IdentityAccess\Application\Query\AuthenticateUser\Exception\InvalidCredentialsException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Mot de passe · Modifier ». Every session of the account closes with
 * the old password, this one included: the admin signs in again.
 */
final class ChangePasswordController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentAdmin $currentAdmin,
        private readonly ConsoleAuditTrail $auditTrail,
        private readonly Security $security,
    ) {
    }

    #[Route(path: '/account/password', name: 'backoffice_account_password', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        if (!$this->isCsrfTokenValid(AccountProfileSaver::CSRF_ID, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $form  = $request->request;
        $admin = $this->currentAdmin->get();

        if ($form->getString('new_password') !== $form->getString('confirmation')) {
            $this->addFlash('error', 'Les deux nouveaux mots de passe ne correspondent pas.');

            return $this->redirectToRoute('backoffice_account');
        }

        try {
            $this->commandBus->dispatch(new ChangeOwnPassword($admin->id, $form->getString('current_password'), $form->getString('new_password')));
        } catch (InvalidCredentialsException) {
            $this->addFlash('error', 'Le mot de passe actuel n’est pas le bon.');

            return $this->redirectToRoute('backoffice_account');
        } catch (UnacceptablePasswordException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('backoffice_account');
        }

        $this->auditTrail->record('admin.password_changed', AuditCategory::ACCESS, 'Mot de passe modifié', targetUserId: $admin->id);

        $response = $this->security->logout(false) ?? $this->redirectToRoute('backoffice_login');
        $this->addFlash('success', 'Mot de passe modifié. Connecte-toi avec le nouveau.');

        return $response;
    }
}
