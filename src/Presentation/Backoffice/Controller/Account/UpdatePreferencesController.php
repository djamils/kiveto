<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Account;

use App\Presentation\Backoffice\Account\AccountProfileSaver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Préférences »: the two e-mails and the display time zone, sent on
 * change.
 */
final class UpdatePreferencesController extends AbstractController
{
    public function __construct(
        private readonly AccountProfileSaver $saver,
    ) {
    }

    #[Route(path: '/account/preferences', name: 'backoffice_account_preferences', methods: ['POST'])]
    public function __invoke(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(AccountProfileSaver::CSRF_ID, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $form    = $request->request;
        $zone    = $form->getString('time_zone');
        $changes = [
            'notify_registry_failure'  => $form->getBoolean('notify_registry_failure'),
            'notify_invitation_expiry' => $form->getBoolean('notify_invitation_expiry'),
        ];

        if (\in_array($zone, ShowAccountController::TIME_ZONES, true)) {
            $changes['time_zone'] = $zone;
        }

        $this->saver->save($changes, 'admin.preferences_updated', 'Préférences du compte modifiées');
        $this->addFlash('success', 'Préférences enregistrées.');

        return $this->redirectToRoute('backoffice_account');
    }
}
