<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Controller;

use App\Context\ClientPortal\Application\Query\GetPublishedDocumentVersionFile\GetPublishedDocumentVersionFile;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Portal\Support\SignedFileUrls;
use App\Presentation\Api\Shared\Problem\ApiProblem;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Storage\FileStorageInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public: the signature stands for the authentication. A withdrawn
 * document, an unknown version or a file already deleted answer 404.
 */
final readonly class SignedDocumentDownloadController
{
    public function __construct(
        private SignedFileUrls $urls,
        private QueryBusInterface $queryBus,
        private FileStorageInterface $storage,
    ) {
    }

    #[Route('/files/documents/{clinicId}/{documentId}/{version}', name: 'api_portal_document_download', requirements: ['version' => '\d{1,6}'], methods: ['GET'])]
    public function __invoke(Request $request, string $clinicId, string $documentId, int $version): Response
    {
        try {
            $this->urls->verify($request);
        } catch (ApiProblem $problem) {
            return PortalProblemMap::response($problem->getType());
        }

        $key = $this->queryBus->ask(new GetPublishedDocumentVersionFile($clinicId, $documentId, $version));

        if (!\is_string($key)) {
            return PortalProblemMap::response(PortalProblemMap::NOT_FOUND);
        }

        try {
            $content = $this->storage->read($key);
        } catch (\RuntimeException) {
            return PortalProblemMap::response(PortalProblemMap::NOT_FOUND);
        }

        return new Response($content, Response::HTTP_OK, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="document.pdf"',
            'Cache-Control'       => 'private, no-store',
        ]);
    }
}
