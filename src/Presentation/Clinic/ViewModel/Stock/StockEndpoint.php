<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Stock;

use App\Presentation\Clinic\Form\Shared\QuantityParser;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * What every write of the stock page shares: the page posts in the background
 * (the module redraws from clinic_stock_data afterwards), so each controller
 * checks the page's token, reads the clinic and who is acting, and answers in
 * JSON — `{ok: true, …}` or a 422 carrying the French reason.
 */
final readonly class StockEndpoint
{
    public function __construct(
        private CsrfTokenManagerInterface $csrf,
        private CurrentClinicContextInterface $currentClinicContext,
        private Security $security,
    ) {
    }

    /** A response when the request must be turned away, null when it may go on. */
    public function guard(Request $request): ?JsonResponse
    {
        if (!$this->csrf->isTokenValid(new CsrfToken(StockPageBuilder::CSRF_ID, $request->request->getString('_token')))) {
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

    /** The user acting, recorded on the movements they write. */
    public function performer(): ?string
    {
        $user = $this->security->getUser();

        return $user instanceof SecurityUser ? $user->id() : null;
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
            ['error' => \is_string($reason) ? $reason : StockErrorMessage::of($reason)],
            JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
        );
    }

    /**
     * A quantity typed in the page, null when it is not one.
     *
     * @return numeric-string|null
     */
    public static function quantity(mixed $raw): ?string
    {
        return \is_scalar($raw) ? QuantityParser::normalize((string) $raw) : null;
    }

    /** A trimmed text field, null when absent or empty. */
    public static function text(Request $request, string $key): ?string
    {
        $raw = mb_trim($request->request->getString($key));

        return '' !== $raw ? $raw : null;
    }

    /**
     * The rows of a list posted as `key[0][field]…`, each one an array of
     * strings.
     *
     * @return list<array<string, string>>
     */
    public static function rows(Request $request, string $key): array
    {
        $rows = [];

        foreach ($request->request->all($key) as $row) {
            if (!\is_array($row)) {
                continue;
            }

            $clean = [];

            foreach ($row as $field => $value) {
                if (\is_string($field) && \is_scalar($value)) {
                    $clean[$field] = mb_trim((string) $value);
                }
            }

            $rows[] = $clean;
        }

        return $rows;
    }
}
