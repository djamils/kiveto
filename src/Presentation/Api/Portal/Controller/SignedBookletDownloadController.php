<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Controller;

use App\Context\ClientPortal\Application\Service\VaccinationBookletFiles;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Portal\Support\SignedFileUrls;
use App\Presentation\Api\Shared\Problem\ApiProblem;
use App\Shared\Domain\Storage\FileStorageInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public: the signature stands for the authentication. The key is rebuilt
 * from the signed animal and hash; a booklet already replaced and deleted
 * answers 404.
 */
final readonly class SignedBookletDownloadController
{
    private const string PDF = '.pdf';

    public function __construct(
        private SignedFileUrls $urls,
        private FileStorageInterface $storage,
    ) {
    }

    #[Route('/files/booklets/{clinicId}/{animalId}/{hash}', name: 'api_portal_booklet_download', requirements: ['animalId' => '[0-9a-fA-F-]{36}', 'hash' => '[0-9a-f]{64}'], methods: ['GET'])]
    public function __invoke(Request $request, string $animalId, string $hash): Response
    {
        try {
            $this->urls->verify($request);
        } catch (ApiProblem $problem) {
            return PortalProblemMap::response($problem->getType());
        }

        try {
            $content = $this->storage->read(VaccinationBookletFiles::KEY_PREFIX . $animalId . '-' . $hash . self::PDF);
        } catch (\RuntimeException) {
            return PortalProblemMap::response(PortalProblemMap::NOT_FOUND);
        }

        return new Response($content, Response::HTTP_OK, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="carnet-de-vaccination.pdf"',
            'Cache-Control'       => 'private, no-store',
        ]);
    }

    /** The content hash of a booklet key `client_portal/booklets/<animalId>-<hash>.pdf`. */
    public static function hashOf(string $key, string $animalId): string
    {
        return substr($key, \strlen(VaccinationBookletFiles::KEY_PREFIX . $animalId . '-'), -\strlen(self::PDF));
    }
}
