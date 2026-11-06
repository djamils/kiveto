<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Registry;

use App\Shared\Application\Bus\QueryBusInterface;
use App\System\PharmaceuticalRegistry\Application\Query\Console\GetRegistryImportFile;
use App\System\PharmaceuticalRegistry\Application\Query\Console\RegistryImportFile;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

/**
 * G3 « Télécharger »: a source file of an import, as the archive keeps it.
 */
final class DownloadRegistryImportFileController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    #[Route(
        path: '/registry/imports/{id}/files/{kind}',
        name: 'backoffice_registry_import_file',
        requirements: ['id' => '[0-9a-f-]{36}', 'kind' => RegistryImportFile::REGISTRY . '|' . RegistryImportFile::DICTIONARY],
        methods: ['GET'],
    )]
    public function __invoke(string $id, string $kind): BinaryFileResponse
    {
        $file = $this->queryBus->ask(new GetRegistryImportFile($id, $kind));

        if (!\is_array($file) || !\is_string($file['path'] ?? null) || !\is_string($file['name'] ?? null)) {
            throw $this->createNotFoundException('Fichier introuvable dans l’archive.');
        }

        $response = new BinaryFileResponse($file['path'], headers: ['Content-Type' => 'application/xml']);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $file['name']);

        return $response;
    }
}
