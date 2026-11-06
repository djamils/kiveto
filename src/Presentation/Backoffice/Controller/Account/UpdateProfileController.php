<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Account;

use App\Presentation\Backoffice\Account\AccountProfileSaver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Profil · Enregistrer »: the name the admin signs the log with.
 */
final class UpdateProfileController extends AbstractController
{
    public function __construct(
        private readonly AccountProfileSaver $saver,
    ) {
    }

    #[Route(path: '/account/profile', name: 'backoffice_account_profile', methods: ['POST'])]
    public function __invoke(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(AccountProfileSaver::CSRF_ID, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        try {
            $this->saver->save(['display_name' => $request->request->getString('display_name')], 'admin.profile_updated', 'Profil modifié');
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('backoffice_account');
        }

        $this->addFlash('success', 'Profil enregistré.');

        return $this->redirectToRoute('backoffice_account');
    }
}
