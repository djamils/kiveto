<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Translation;

use App\Presentation\Backoffice\Security\CurrentAdmin;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Translation\Application\Command\DeleteTranslation\DeleteTranslation;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class DeleteTranslationController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentAdmin $currentAdmin,
    ) {
    }

    #[Route(path: '/translations/delete', name: 'backoffice_translations_delete', methods: ['POST'])]
    public function __invoke(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(UpsertTranslationController::CSRF_ID, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $form   = $request->request;
        $scope  = trim($form->getString('scope'));
        $locale = trim($form->getString('locale'));
        $domain = trim($form->getString('domain'));
        $key    = trim($form->getString('key'));
        $back   = ['scope' => $scope, 'locale' => $locale, 'domain' => $domain];

        if ('' === $scope || '' === $locale || '' === $domain || '' === $key) {
            $this->addFlash('error', 'Paramètres manquants pour la suppression.');

            return $this->redirectToRoute('backoffice_translations', $back);
        }

        $this->commandBus->dispatch(new DeleteTranslation(
            scope: $scope,
            locale: $locale,
            domain: $domain,
            key: $key,
            actorId: $this->currentAdmin->get()->id,
        ));

        $this->addFlash('success', 'Traduction supprimée.');

        return $this->redirectToRoute('backoffice_translations', $back);
    }
}
