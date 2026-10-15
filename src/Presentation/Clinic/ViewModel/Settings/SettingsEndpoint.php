<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Settings;

use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\ClinicSettingsView;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\GetClinicSettings;
use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * What every save of the settings page shares. The page saves as it goes: each
 * change posts the whole section it belongs to, as JSON in a `values` field,
 * with the page's token. Each controller checks the right that section needs,
 * reads the values through here and answers `{ok: true}` or a 422 carrying the
 * French reason and, when it can tell, the field it is about.
 */
final readonly class SettingsEndpoint
{
    public function __construct(
        private CsrfTokenManagerInterface $csrf,
        private CurrentClinicContextInterface $currentClinicContext,
        private ClinicPermissionGuard $guard,
        private QueryBusInterface $queryBus,
    ) {
    }

    /**
     * A response when the request must be turned away, null when it may go on.
     *
     * @param string|null $permission null for what every member may change
     */
    public function guard(Request $request, ?string $permission): ?JsonResponse
    {
        if (null !== $permission && !$this->guard->has($permission)) {
            return new JsonResponse(['error' => 'Vous n’avez pas accès à ces réglages.'], JsonResponse::HTTP_FORBIDDEN);
        }

        if (!$this->csrf->isTokenValid(new CsrfToken(SettingsPageBuilder::CSRF_ID, $request->request->getString('_token')))) {
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

    public function settings(): ClinicSettingsView
    {
        $settings = $this->queryBus->ask(new GetClinicSettings($this->clinicId()));
        \assert($settings instanceof ClinicSettingsView);

        return $settings;
    }

    /**
     * The section as the page posted it.
     *
     * @return array<array-key, mixed>
     */
    public static function values(Request $request): array
    {
        try {
            $values = json_decode($request->request->getString('values'), true, 16, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return \is_array($values) ? $values : [];
    }

    /**
     * A trimmed text value, null when absent or empty.
     *
     * @param array<array-key, mixed> $values
     */
    public static function text(array $values, string $key): ?string
    {
        $raw = $values[$key] ?? null;

        if (\is_int($raw)) {
            $raw = (string) $raw;
        }

        if (!\is_string($raw)) {
            return null;
        }

        $raw = mb_trim(preg_replace('/\s+/u', ' ', $raw) ?? $raw);

        return '' !== $raw ? $raw : null;
    }

    /**
     * A text value without the spaces the field adds for reading: « 812 456 903 00028 ».
     *
     * @param array<array-key, mixed> $values
     */
    public static function compact(array $values, string $key): ?string
    {
        $text = self::text($values, $key);

        return null !== $text ? str_replace(' ', '', $text) : null;
    }

    /**
     * A whole number, null when absent or not one.
     *
     * @param array<array-key, mixed> $values
     */
    public static function int(array $values, string $key): ?int
    {
        $raw = $values[$key] ?? null;

        if (\is_int($raw)) {
            return $raw;
        }

        return \is_string($raw) && 1 === preg_match('/^\d{1,5}$/', mb_trim($raw)) ? (int) mb_trim($raw) : null;
    }

    /**
     * A phone number as typed in the page (« 04 91 00 00 00 », « +33 4 … »,
     * « 0033 4 … »), in the E.164 form the domain keeps; null when empty.
     */
    public static function phone(?string $typed): ?string
    {
        if (null === $typed) {
            return null;
        }

        $digits = preg_replace('/[\s.\-()]+/', '', $typed) ?? $typed;

        if (str_starts_with($digits, '00')) {
            return '+' . mb_substr($digits, 2);
        }

        // A French national number: trunk 0 and nine digits.
        if (1 === preg_match('/^0\d{9}$/', $digits)) {
            return '+33' . mb_substr($digits, 1);
        }

        return '' !== $digits ? $digits : null;
    }

    /**
     * @param array<string, mixed> $extra
     */
    public function ok(array $extra = []): JsonResponse
    {
        return new JsonResponse(['ok' => true] + $extra);
    }

    /**
     * @param string|null $field the setting the reason is about, when known
     */
    public function refuse(string $reason, ?string $field = null): JsonResponse
    {
        return new JsonResponse(
            ['error' => $reason] + (null !== $field ? ['field' => $field] : []),
            JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
        );
    }
}
