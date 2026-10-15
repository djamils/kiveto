<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Team;

use App\Context\Clinic\Application\Exception\CannotChangeRoleWhileVeterinaryCredentialsExist;
use App\Context\Clinic\Application\Exception\CannotRegisterCredentialsForNonVeterinarianRole;
use App\Context\Clinic\Application\Exception\ClinicMembershipAlreadyExistsException;
use App\Context\Clinic\Application\Exception\LastClinicAdministratorException;
use App\Context\Clinic\Application\Port\StaffProfileReadRepositoryInterface;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\ClinicSettingsView;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\GetClinicSettings;
use App\Context\Clinic\Application\Query\Staff\ListAllMemberships\ListAllMemberships;
use App\Context\Clinic\Application\Query\Staff\ListAllMemberships\MembershipCollection;
use App\Context\Clinic\Application\Query\Staff\ListAllMemberships\MembershipListItem;
use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * What every write of the team page shares: it posts in the background (the
 * module redraws from clinic_settings_team_data afterwards), so each controller
 * checks the right to manage the team and the page's token, resolves the
 * member inside the current clinic — a membership id off a URL never reaches a
 * command unless it belongs here — and answers in JSON: `{ok: true, …}` or a
 * 422 carrying the French reason.
 */
final readonly class TeamEndpoint
{
    public function __construct(
        private CsrfTokenManagerInterface $csrf,
        private CurrentClinicContextInterface $currentClinicContext,
        private ClinicPermissionGuard $guard,
        private QueryBusInterface $queryBus,
        private StaffProfileReadRepositoryInterface $profiles,
        private Security $security,
    ) {
    }

    /** A response when the request must be turned away, null when it may go on. */
    public function guard(Request $request): ?JsonResponse
    {
        if (!$this->guard->has(ClinicPermissionGuard::MANAGE_STAFF)) {
            return new JsonResponse(['error' => 'Vous ne gérez pas l’équipe de cette clinique.'], JsonResponse::HTTP_FORBIDDEN);
        }

        if (!$this->csrf->isTokenValid(new CsrfToken(TeamPageBuilder::CSRF_ID, $request->request->getString('_token')))) {
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

    /** The membership of the current clinic with this id, null when there is none. */
    public function member(string $membershipId): ?MembershipListItem
    {
        $collection = $this->queryBus->ask(new ListAllMemberships(clinicId: $this->clinicId()));
        \assert($collection instanceof MembershipCollection);

        foreach ($collection->memberships as $membership) {
            if ($membership->membershipId === $membershipId) {
                return $membership;
            }
        }

        return null;
    }

    /** The profile behind a membership of this clinic, null when it has none. */
    public function profileId(string $membershipId): ?string
    {
        return $this->profiles->findByMembershipId($membershipId)?->profileId;
    }

    /** Whether the membership is the one of the member using the page. */
    public function isViewer(MembershipListItem $membership): bool
    {
        $user = $this->security->getUser();

        return $user instanceof SecurityUser && $user->id() === $membership->userId;
    }

    public function timeZone(): \DateTimeZone
    {
        $settings = $this->queryBus->ask(new GetClinicSettings($this->clinicId()));

        return new \DateTimeZone($settings instanceof ClinicSettingsView ? $settings->timeZone : 'Europe/Paris');
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
            ['error' => \is_string($reason) ? $reason : self::message($reason)],
            JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
        );
    }

    public function unknown(): JsonResponse
    {
        return $this->refuse('Ce membre ne fait pas partie de cette clinique.');
    }

    /** A trimmed text field, null when absent or empty. */
    public static function text(Request $request, string $key): ?string
    {
        $raw = mb_trim($request->request->getString($key));

        return '' !== $raw ? $raw : null;
    }

    /**
     * A phone number as typed in the page (« 06 12 34 56 78 », « +33 6 … »,
     * « 0033 6 … »), in the E.164 form the domain keeps; null when empty.
     */
    public static function phone(?string $typed): ?string
    {
        if (null === $typed) {
            return null;
        }

        $digits = preg_replace('/[\s.\-()]+/', '', $typed) ?? $typed;

        if (str_starts_with($digits, '00')) {
            return '+' . substr($digits, 2);
        }

        // A French national number: trunk 0 and nine digits.
        if (1 === preg_match('/^0\d{9}$/', $digits)) {
            return '+33' . substr($digits, 1);
        }

        return '' !== $digits ? $digits : null;
    }

    /**
     * A day typed in the page, at the start of that day in the clinic's time
     * zone; null when absent or not a date.
     */
    public function startOfDay(?string $date): ?\DateTimeImmutable
    {
        return $this->day($date)?->setTimezone(new \DateTimeZone('UTC'));
    }

    /** The last second of a day typed in the page: an access « ends at midnight ». */
    public function endOfDay(?string $date): ?\DateTimeImmutable
    {
        return $this->day($date)?->setTime(23, 59, 59)->setTimezone(new \DateTimeZone('UTC'));
    }

    public static function message(\Throwable $exception): string
    {
        return match (true) {
            $exception instanceof LastClinicAdministratorException => 'Impossible : plus personne ne pourrait gérer la clinique.',

            $exception instanceof CannotChangeRoleWhileVeterinaryCredentialsExist => 'Ce membre est inscrit à l’Ordre : effacez d’abord son n° d’inscription pour lui donner un rôle non vétérinaire.',

            $exception instanceof CannotRegisterCredentialsForNonVeterinarianRole => 'Seul un membre au rôle de vétérinaire peut être inscrit à l’Ordre.',

            $exception instanceof ClinicMembershipAlreadyExistsException => 'Cette personne fait déjà partie de l’équipe.',

            str_contains($exception->getMessage(), 'phone number') => 'Ce numéro de téléphone n’est pas valide.',

            str_contains($exception->getMessage(), 'registration number') => 'Le n° d’inscription à l’Ordre fait au plus 32 caractères.',

            str_contains($exception->getMessage(), 'validFrom') => 'La fin de l’accès ne peut pas précéder son début.',

            str_contains($exception->getMessage(), 'color') || str_contains($exception->getMessage(), 'colour') => 'Cette couleur n’est pas valide (format #rrggbb).',

            str_contains($exception->getMessage(), 'Display name') || str_contains($exception->getMessage(), 'display name') => 'Le nom affiché est trop long.',

            // What the domain raises without a name of its own still says what
            // it refused.
            default => $exception->getMessage(),
        };
    }

    /**
     * The day at midnight in the clinic's time zone. The instants handed to
     * the commands are then moved to UTC: the columns are named `_utc` and
     * Doctrine writes a date's wall-clock time as it is.
     */
    private function day(?string $date): ?\DateTimeImmutable
    {
        if (null === $date) {
            return null;
        }

        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, $this->timeZone());

        return false === $day ? null : $day;
    }
}
