<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Registry;

use App\Presentation\Backoffice\Audit\ConsoleAuditTrail;
use App\Presentation\Backoffice\Security\CurrentAdmin;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\Audit\Domain\ValueObject\AuditCategory;
use App\System\PharmaceuticalRegistry\Application\Command\RequestRegistryImport\RequestRegistryImport;
use App\System\PharmaceuticalRegistry\Application\Query\Console\ListRegistryImports;
use App\System\PharmaceuticalRegistry\Application\Query\Console\RegistryStatusView;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Lancer l'import »: a worker imports the files waiting, the page shows
 * the import running. Audited.
 */
final class LaunchRegistryImportController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        #[Autowire(service: 'messenger.bus.command')]
        private readonly MessageBusInterface $asyncCommands,
        private readonly ConsoleAuditTrail $auditTrail,
        private readonly CurrentAdmin $currentAdmin,
    ) {
    }

    #[Route(path: '/registry/import', name: 'backoffice_registry_import_launch', methods: ['POST'])]
    public function __invoke(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(ShowRegistryController::CSRF_ID, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $status = $this->queryBus->ask(new ListRegistryImports(1));
        \assert($status instanceof RegistryStatusView);

        if (true === $status->latest()?->isRunning()) {
            $this->addFlash('info', 'Un import est déjà en cours.');

            return $this->redirectToRoute('backoffice_registry');
        }

        if (!$status->filesReady) {
            $this->addFlash('error', 'Aucun fichier à importer : dépose amm.xml et dict.xml dans storage/pharma-registry/ANMV/current/.');

            return $this->redirectToRoute('backoffice_registry');
        }

        if ($status->sameAsLast) {
            $this->addFlash('info', 'Ces fichiers sont ceux du dernier import (même empreinte SHA-256) : rien à importer.');

            return $this->redirectToRoute('backoffice_registry');
        }

        $this->asyncCommands->dispatch(new RequestRegistryImport('ANMV', $this->currentAdmin->get()->name));
        $this->auditTrail->record('registry.import_requested', AuditCategory::REGISTRY, 'Import du registre ANMV lancé');
        $this->addFlash('success', 'Import lancé : le registre se met à jour en arrière-plan.');

        return $this->redirectToRoute('backoffice_registry');
    }
}
