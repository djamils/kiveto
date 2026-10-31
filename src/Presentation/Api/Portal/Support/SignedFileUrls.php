<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Support;

use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Shared\Problem\ApiProblem;
use Symfony\Component\HttpFoundation\Exception\SignedUriException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The 5-minute signed URLs of the Portal's file downloads (`/files/…`). The
 * signature covers the whole path, so the clinic, the document and its
 * version cannot be swapped. UriSigner reads the framework clock, which the
 * tests move with `mockTime()`.
 */
final readonly class SignedFileUrls
{
    public const string VALIDITY = 'PT5M';

    public function __construct(
        private UriSigner $signer,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /**
     * @param array<string, string|int> $parameters
     */
    public function sign(string $routeName, array $parameters): string
    {
        return $this->signer->sign(
            $this->urls->generate($routeName, $parameters, UrlGeneratorInterface::ABSOLUTE_URL),
            new \DateInterval(self::VALIDITY),
        );
    }

    /**
     * @throws ApiProblem `not_found` for a missing, tampered or expired signature
     */
    public function verify(Request $request): void
    {
        try {
            $this->signer->verify($request);
        } catch (SignedUriException) {
            throw PortalProblemMap::problem(PortalProblemMap::NOT_FOUND);
        }
    }
}
