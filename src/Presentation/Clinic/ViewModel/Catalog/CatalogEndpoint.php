<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Catalog;

use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * What every write of the catalogue page shares: the page posts in the
 * background (the module redraws from clinic_catalogue_data afterwards), so
 * each controller checks the page's token, reads the clinic, and answers in
 * JSON — `{ok: true, …}` or a 422 carrying the French reason.
 */
final readonly class CatalogEndpoint
{
    public function __construct(
        private CsrfTokenManagerInterface $csrf,
        private CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    /** A response when the request must be turned away, null when it may go on. */
    public function guard(Request $request): ?JsonResponse
    {
        if (!$this->csrf->isTokenValid(new CsrfToken(CatalogPageBuilder::CSRF_ID, $request->request->getString('_token')))) {
            return new JsonResponse(['error' => 'Session expirée : rechargez la page.'], JsonResponse::HTTP_FORBIDDEN);
        }

        return null;
    }

    public function clinicId(): string
    {
        $clinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $clinicId);

        return $clinicId->toString();
    }

    /**
     * @param array<string, mixed> $extra
     */
    public function ok(array $extra = []): JsonResponse
    {
        return new JsonResponse(['ok' => true] + $extra);
    }

    public function refuse(\Throwable|string $reason): JsonResponse
    {
        return new JsonResponse(
            ['error' => \is_string($reason) ? $reason : CatalogErrorMessage::of($reason)],
            JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
        );
    }

    /**
     * An amount in minor units sent as an integer string, or null when the
     * field is absent, empty or not a whole number of cents.
     */
    public static function cents(Request $request, string $key): ?int
    {
        $raw = mb_trim($request->request->getString($key));

        return 1 === preg_match('/^\d{1,9}$/', $raw) ? (int) $raw : null;
    }

    /** A trimmed text field, null when absent or empty. */
    public static function text(Request $request, string $key): ?string
    {
        $raw = mb_trim($request->request->getString($key));

        return '' !== $raw ? $raw : null;
    }
}
