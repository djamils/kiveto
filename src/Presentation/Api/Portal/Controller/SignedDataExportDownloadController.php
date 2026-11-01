<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Controller;

use App\Context\ClientPortal\Application\Query\GetDataExportFile\GetDataExportFileHandler;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Portal\Support\SignedFileUrls;
use App\Presentation\Api\Shared\Problem\ApiProblem;
use App\Shared\Domain\Storage\FileStorageInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public: the signature stands for the authentication. An archive already
 * deleted (48 hours, account deletion) answers 404.
 */
final readonly class SignedDataExportDownloadController
{
    public function __construct(
        private SignedFileUrls $urls,
        private FileStorageInterface $storage,
    ) {
    }

    #[Route('/files/exports/{exportId}', name: 'api_portal_data_export_download', requirements: ['exportId' => '[0-9a-fA-F-]{36}'], methods: ['GET'])]
    public function __invoke(Request $request, string $exportId): Response
    {
        try {
            $this->urls->verify($request);
        } catch (ApiProblem $problem) {
            return PortalProblemMap::response($problem->getType());
        }

        try {
            $content = $this->storage->read(GetDataExportFileHandler::storageKey($exportId));
        } catch (\RuntimeException) {
            return PortalProblemMap::response(PortalProblemMap::NOT_FOUND);
        }

        return new Response($content, Response::HTTP_OK, [
            'Content-Type'        => 'application/zip',
            'Content-Disposition' => 'attachment; filename="kiveto-export.zip"',
            'Cache-Control'       => 'private, no-store',
        ]);
    }
}
