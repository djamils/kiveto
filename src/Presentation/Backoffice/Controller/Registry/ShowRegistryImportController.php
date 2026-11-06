<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Registry;

use App\Presentation\Backoffice\Controller\Clinic\ShowClinicController;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\PharmaceuticalRegistry\Application\Query\Console\GetRegistryImport;
use App\System\PharmaceuticalRegistry\Application\Query\Console\RegistryImportDetail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * G4 — one import: what it added, changed and withdrew.
 */
final class ShowRegistryImportController extends AbstractController
{
    public const array KINDS = ['all' => null, 'created' => 'CREATE', 'updated' => 'UPDATE', 'withdrawn' => 'WITHDRAW'];

    public function __construct(
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    #[Route(path: '/registry/imports/{id}', name: 'backoffice_registry_import', requirements: ['id' => ShowClinicController::UUID], methods: ['GET'])]
    public function __invoke(Request $request, string $id): Response
    {
        $kind   = $request->query->getString('kind', 'all');
        $kind   = \array_key_exists($kind, self::KINDS) ? $kind : 'all';
        $detail = $this->queryBus->ask(new GetRegistryImport($id, self::KINDS[$kind], max(1, $request->query->getInt('page', 1))));

        if (!$detail instanceof RegistryImportDetail) {
            throw $this->createNotFoundException('Import inconnu.');
        }

        return $this->render('backoffice/console/registry/import.html.twig', [
            'detail'   => $detail,
            'snapshot' => $detail->import,
            'kind'     => $kind,
        ]);
    }
}
