<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Problem;

use App\Presentation\Api\Shared\Problem\ApiProblem;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * The single table of the Portal API's business errors: code → HTTP status
 * and title. Every error leaves as `application/problem+json` with `type` =
 * the code; the app switches on `type`, the title is informative only.
 */
final class PortalProblemMap
{
    public const string UNAUTHORIZED            = 'unauthorized';
    public const string TOKEN_EXPIRED           = 'token_expired';
    public const string REFRESH_REVOKED         = 'refresh_revoked';
    public const string OTP_INVALID             = 'otp_invalid';
    public const string OTP_EXPIRED             = 'otp_expired';
    public const string OTP_ATTEMPTS_EXCEEDED   = 'otp_attempts_exceeded';
    public const string OTP_RATE_LIMITED        = 'otp_rate_limited';
    public const string OTP_CHALLENGE_NOT_FOUND = 'otp_challenge_not_found';
    public const string PHONE_NOT_SUPPORTED     = 'phone_not_supported';
    public const string ACCOUNT_DISABLED        = 'account_disabled';
    public const string VALIDATION_FAILED       = 'validation_failed';

    /** @var array<string, array{int, string}> */
    private const array PROBLEMS = [
        self::UNAUTHORIZED            => [401, 'Authentification requise.'],
        self::TOKEN_EXPIRED           => [401, 'Session expirée.'],
        self::REFRESH_REVOKED         => [401, 'Session révoquée : reconnectez-vous.'],
        self::OTP_INVALID             => [422, 'Code incorrect.'],
        self::OTP_EXPIRED             => [422, 'Code expiré.'],
        self::OTP_ATTEMPTS_EXCEEDED   => [422, 'Trop d\'essais : demandez un nouveau code.'],
        self::OTP_RATE_LIMITED        => [429, 'Trop de demandes de code : réessayez plus tard.'],
        self::OTP_CHALLENGE_NOT_FOUND => [404, 'Code de connexion introuvable.'],
        self::PHONE_NOT_SUPPORTED     => [422, 'Ce numéro de mobile n\'est pas encore pris en charge par le Portal.'],
        self::ACCOUNT_DISABLED        => [403, 'Ce compte est désactivé.'],
        self::VALIDATION_FAILED       => [422, 'Données invalides.'],
    ];

    /**
     * @param array<string, string> $headers
     */
    public static function problem(string $code, array $headers = []): ApiProblem
    {
        [$status, $title] = self::PROBLEMS[$code] ?? throw new \LogicException(\sprintf('Unknown Portal API error "%s".', $code));

        return new ApiProblem(type: $code, status: $status, title: $title, headers: $headers);
    }

    /** For the errors raised outside API Platform (the security layer). */
    public static function response(string $code): JsonResponse
    {
        $problem = self::problem($code);

        return new JsonResponse(
            ['type' => $code, 'title' => $problem->getTitle(), 'status' => $problem->getStatus()],
            $problem->getStatus(),
            ['Content-Type' => 'application/problem+json'],
        );
    }

    public static function status(string $code): int
    {
        return self::problem($code)->getStatus();
    }
}
