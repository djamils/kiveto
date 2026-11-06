<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\User;

use App\Presentation\Backoffice\Controller\Clinic\ShowClinicController;
use App\Presentation\Backoffice\User\ConsoleUserManager;
use App\Presentation\Backoffice\ViewModel\ConsoleDirectory;
use App\Presentation\Backoffice\ViewModel\User\UserSheetBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * E2 / E3 / E4 — a clinic account's sheet, with its « Désactiver », « Ajouter
 * à une clinique » and « Modifier l'e-mail » windows.
 */
final class ShowUserController extends AbstractController
{
    public const string CSRF_ID = 'backoffice_user';

    private const array DIALOGS = ['disable', 'add', 'email'];

    public function __construct(
        private readonly UserSheetBuilder $builder,
    ) {
    }

    #[Route(path: '/users/{id}', name: 'backoffice_user_show', requirements: ['id' => ShowClinicController::UUID], methods: ['GET'])]
    public function __invoke(Request $request, string $id): Response
    {
        $sheet = $this->builder->build($id);

        if (null === $sheet) {
            throw $this->createNotFoundException('Utilisateur introuvable.');
        }

        $dialog  = $request->query->getString('dialog');
        $dialog  = \in_array($dialog, self::DIALOGS, true) ? $dialog : '';
        $session = $request->getSession();
        // The refusal of a window is shown inside it, not behind it.
        $error = '' !== $dialog && $session instanceof FlashBagAwareSessionInterface ? ($session->getFlashBag()->get('error')[0] ?? null) : null;

        return $this->render('backoffice/console/users/show.html.twig', [
            'sheet'            => $sheet,
            'open_dialog'      => '' !== $dialog ? 'dialog-' . $dialog : '',
            $dialog . '_error' => $error,
            'reasons'          => ConsoleUserManager::DISABLE_REASONS,
            'roles'            => array_combine(['VETERINARY', 'VETERINARY_ASSISTANT', 'RECEPTIONIST', 'MANAGER'], array_map(ConsoleDirectory::roleLabel(...), ['VETERINARY', 'VETERINARY_ASSISTANT', 'RECEPTIONIST', 'MANAGER'])),
        ]);
    }
}
