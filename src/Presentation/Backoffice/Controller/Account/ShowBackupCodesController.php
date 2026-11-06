<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Account;

use App\Presentation\Backoffice\Security\BackofficeAuthenticator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The ten backup codes just made, shown once: a reload shows nothing more.
 */
final class ShowBackupCodesController extends AbstractController
{
    #[Route(path: '/account/backup-codes', name: 'backoffice_backup_codes', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $codes = $request->getSession()->get(BackofficeAuthenticator::FRESH_BACKUP_CODES);
        $request->getSession()->remove(BackofficeAuthenticator::FRESH_BACKUP_CODES);

        if (!\is_array($codes) || [] === $codes) {
            return $this->redirectToRoute('backoffice_account');
        }

        $response = $this->render('backoffice/console/account/backup_codes.html.twig', ['codes' => $codes]);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
