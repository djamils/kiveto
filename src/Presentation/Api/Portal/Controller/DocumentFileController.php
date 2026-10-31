<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Controller;

use App\Context\ClientPortal\Application\Command\MarkDocumentRead\MarkDocumentRead;
use App\Context\ClientPortal\Application\Query\GetPublishedDocumentFile\GetPublishedDocumentFile;
use App\Context\ClientPortal\Application\Query\GetPublishedDocumentFile\PublishedDocumentFile;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Portal\Security\PortalApiUser;
use App\Presentation\Api\Portal\Support\PortalClinicGuard;
use App\Presentation\Api\Portal\Support\SignedFileUrls;
use App\Presentation\Api\Shared\Problem\ApiProblem;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Marks the document read for the caller only, then redirects to a signed
 * URL valid 5 minutes, over the clinic, the document and its served version.
 */
final readonly class DocumentFileController
{
    public function __construct(
        private PortalClinicGuard $guard,
        private QueryBusInterface $queryBus,
        private CommandBusInterface $commandBus,
        private SignedFileUrls $urls,
    ) {
    }

    #[Route('/clinics/{clinicId}/documents/{documentId}/file', name: 'api_portal_document_file', methods: ['GET'])]
    public function __invoke(string $clinicId, string $documentId, #[CurrentUser] PortalApiUser $user): Response
    {
        try {
            $this->guard->require($user->id(), $clinicId);
        } catch (ApiProblem $problem) {
            return PortalProblemMap::response($problem->getType());
        }

        $file = $this->queryBus->ask(new GetPublishedDocumentFile($user->id(), $clinicId, $documentId));

        if (!$file instanceof PublishedDocumentFile) {
            return PortalProblemMap::response(PortalProblemMap::NOT_FOUND);
        }

        $this->commandBus->dispatch(new MarkDocumentRead($user->id(), $file->documentId));

        $response = new RedirectResponse($this->urls->sign('api_portal_document_download', [
            'clinicId'   => $clinicId,
            'documentId' => $file->documentId,
            'version'    => $file->version,
        ]));
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
