<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Controller;

use App\Context\ClientPortal\Application\Query\GetDataExportFile\DataExportFile;
use App\Context\ClientPortal\Application\Query\GetDataExportFile\GetDataExportFile;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Portal\Security\PortalApiUser;
use App\Presentation\Api\Portal\Support\SignedFileUrls;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * One of the caller's exports, ready and within its 48 hours: redirects to
 * a signed URL valid 5 minutes. Any other export answers 404.
 */
final readonly class DataExportFileController
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private SignedFileUrls $urls,
    ) {
    }

    #[Route('/me/data-exports/{exportId}/file', name: 'api_portal_data_export_file', methods: ['GET'])]
    public function __invoke(string $exportId, #[CurrentUser] PortalApiUser $user): Response
    {
        $file = $this->queryBus->ask(new GetDataExportFile($user->id(), $exportId));

        if (!$file instanceof DataExportFile) {
            return PortalProblemMap::response(PortalProblemMap::NOT_FOUND);
        }

        $response = new RedirectResponse($this->urls->sign('api_portal_data_export_download', ['exportId' => $file->exportId]));
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
