<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Translation;

use App\Presentation\Backoffice\Security\CurrentAdmin;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Translation\Application\Command\UpsertTranslation\UpsertTranslation;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class UpsertTranslationController extends AbstractController
{
    public const string CSRF_ID = 'backoffice_translation';

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentAdmin $currentAdmin,
    ) {
    }

    #[Route(path: '/translations/upsert', name: 'backoffice_translations_upsert', methods: ['POST'])]
    public function __invoke(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(self::CSRF_ID, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $form   = $request->request;
        $scope  = trim($form->getString('scope'));
        $locale = trim($form->getString('locale'));
        $domain = trim($form->getString('domain'));
        $key    = trim($form->getString('key'));
        $value  = $form->getString('value');
        $back   = ['scope' => $scope, 'locale' => $locale, 'domain' => $domain];

        if ('' === $scope || '' === $locale || '' === $domain || '' === $key || '' === $value) {
            $this->addFlash('error', 'Tous les champs sont obligatoires.');

            return $this->redirectToRoute('backoffice_translations', $back);
        }

        $description = trim($form->getString('description'));

        $this->commandBus->dispatch(new UpsertTranslation(
            scope: $scope,
            locale: $locale,
            domain: $domain,
            key: $key,
            value: $value,
            description: '' !== $description ? $description : null,
            actorId: $this->currentAdmin->get()->id,
        ));

        $this->addFlash('success', 'Traduction enregistrée.');

        return $this->redirectToRoute('backoffice_translations', $back + ['key' => $key]);
    }
}
