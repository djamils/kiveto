<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Problem;

use ApiPlatform\Validator\Exception\ValidationException;
use App\Presentation\Api\Shared\Problem\ApiProblem;
use App\Presentation\Api\Shared\Problem\ProblemFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * The single table of the Portal API's business errors: code → HTTP status
 * and title. Every error leaves as `application/problem+json` with `type` =
 * the code; the app switches on `type`, the title is informative only.
 * Shared API pieces (the Idempotency-Key decorator) build theirs through
 * ProblemFactoryInterface.
 */
final class PortalProblemMap implements ProblemFactoryInterface
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
    public const string INVITATION_INVALID      = 'invitation_invalid';
    public const string INVITATION_EXPIRED      = 'invitation_expired';
    public const string INVITATION_ALREADY_USED = 'invitation_already_used';
    public const string ONBOARDING_INVALID      = 'onboarding_invalid';
    public const string TERMS_OUTDATED          = 'terms_outdated';
    public const string VALIDATION_FAILED       = 'validation_failed';
    public const string NOT_FOUND               = 'not_found';
    public const string CURSOR_INVALID          = 'cursor_invalid';
    public const string SLOT_TAKEN              = 'slot_taken';
    public const string CANCELLATION_CLOSED     = 'cancellation_window_closed';
    public const string NOT_MODIFIABLE          = 'appointment_not_modifiable';
    public const string BOOKING_NOT_OPEN        = 'booking_not_open';
    public const string PET_NOT_BOOKABLE        = 'pet_not_bookable';
    public const string EXPORT_RATE_LIMITED     = 'export_rate_limited';
    public const string EMAIL_TAKEN             = 'email_taken';
    public const string CONFLICT                = 'conflict';

    /** The code of every violation built by violation(). */
    private const string VIOLATION_CODE = 'portal_rule';

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
        self::INVITATION_INVALID      => [404, 'Invitation introuvable.'],
        self::INVITATION_EXPIRED      => [410, 'Invitation expirée : demandez à votre clinique de la renvoyer.'],
        self::INVITATION_ALREADY_USED => [410, 'Invitation déjà utilisée.'],
        self::ONBOARDING_INVALID      => [401, 'Jeton d\'invitation expiré : reconnectez-vous avec votre numéro.'],
        self::TERMS_OUTDATED          => [422, 'Les conditions d\'utilisation ont changé : acceptez la nouvelle version.'],
        self::VALIDATION_FAILED       => [422, 'Données invalides.'],
        self::NOT_FOUND               => [404, 'Ressource introuvable.'],
        self::CURSOR_INVALID          => [400, 'Curseur de pagination invalide.'],
        self::SLOT_TAKEN              => [409, 'Ce créneau n\'est plus disponible : choisissez-en un autre.'],
        self::CANCELLATION_CLOSED     => [422, 'Le délai d\'annulation ou de report de ce rendez-vous est dépassé : contactez la clinique.'],
        self::NOT_MODIFIABLE          => [422, 'Ce rendez-vous ne peut plus être modifié.'],
        self::BOOKING_NOT_OPEN        => [422, 'La prise de rendez-vous en ligne n\'est pas ouverte pour cette clinique.'],
        self::PET_NOT_BOOKABLE        => [422, 'Cet animal ne peut pas être inscrit à un rendez-vous en ligne.'],
        self::EXPORT_RATE_LIMITED     => [429, 'Un export de vos données a déjà été demandé : réessayez dans 24 heures.'],
        self::EMAIL_TAKEN             => [422, 'Cette adresse e-mail est déjà vérifiée par un autre compte.'],
        self::CONFLICT                => [409, 'Modification concurrente : rechargez puis réessayez.'],
        self::IDEMPOTENCY_KEY_MISSING => [400, 'En-tête Idempotency-Key manquant ou invalide.'],
        self::IDEMPOTENCY_KEY_REUSED  => [422, 'Cette clé Idempotency-Key a déjà servi pour une autre requête.'],
    ];

    /**
     * @param array<string, string> $headers
     */
    public static function problem(string $code, array $headers = [], ?string $detail = null, ?\Throwable $previous = null): ApiProblem
    {
        [$status, $title] = self::PROBLEMS[$code] ?? throw new \LogicException(\sprintf('Unknown Portal API error "%s".', $code));

        return new ApiProblem(type: $code, status: $status, title: $title, detail: $detail, headers: $headers, previous: $previous);
    }

    public function create(string $code, ?string $detail = null, array $headers = [], ?\Throwable $previous = null): ApiProblem
    {
        return self::problem($code, $headers, $detail, $previous);
    }

    /**
     * A `validation_failed` on one input the operation's constraints could
     * not check (a rule of the query): rendered by API Platform with its
     * `violations[]`, typed by ValidationProblemTypeListener.
     */
    public static function violation(string $propertyPath, string $message, mixed $invalidValue = null): ValidationException
    {
        return new ValidationException(
            new ConstraintViolationList([new ConstraintViolation($message, $message, [], null, $propertyPath, $invalidValue, null, self::VIOLATION_CODE)]),
            errorTitle: self::problem(self::VALIDATION_FAILED)->getTitle(),
        );
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
