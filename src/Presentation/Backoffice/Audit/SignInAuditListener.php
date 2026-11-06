<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Audit;

use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Context\Clinic\Application\Query\Staff\GetStaffPrintedName\GetStaffPrintedName;
use App\Presentation\Backoffice\Security\BackofficeAuthenticator;
use App\Presentation\Backoffice\Security\CurrentAdmin;
use App\Presentation\Backoffice\Security\InvalidSecondFactorException;
use App\Presentation\Backoffice\Security\SecondFactorRequiredException;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\System\Audit\Application\Command\RecordAuditEntry\RecordAuditEntry;
use App\System\Audit\Domain\ValueObject\ActorKind;
use App\System\Audit\Domain\ValueObject\AuditCategory;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

/**
 * Sign-ins of the console and of the VetApp, granted or refused, in the
 * audit log (« Accès »). A refusal for too many attempts is not written: the
 * rate limiter already stops the flood it would be.
 */
final readonly class SignInAuditListener
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private QueryBusInterface $queryBus,
        private CurrentAdmin $currentAdmin,
        private CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    #[AsEventListener(event: LoginSuccessEvent::class)]
    public function onSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof SecurityUser || $event->getPassport()->hasBadge(SilentSignInBadge::class)) {
            return;
        }

        if (UserType::CLINIC === $user->type()) {
            $this->recordClinicSignIn($user, $event->getRequest());

            return;
        }

        if (UserType::BACKOFFICE === $user->type()) {
            $this->record(
                kind: ActorKind::KIVETO,
                actorId: $user->id(),
                actorName: $this->currentAdmin->find()->name ?? $user->getUserIdentifier(),
                action: 'admin.login',
                summary: 'Connexion au backoffice',
                request: $event->getRequest(),
                details: \is_string($method = $event->getRequest()->attributes->get(BackofficeAuthenticator::METHOD_ATTRIBUTE)) ? ['Second facteur' => $method] : [],
            );
        }
    }

    #[AsEventListener(event: LoginFailureEvent::class)]
    public function onFailure(LoginFailureEvent $event): void
    {
        $exception = $event->getException();

        // Too many attempts: the limiter stops the flood; a missing second
        // factor is the next step of a right password, not a refusal.
        if ($exception instanceof TooManyLoginAttemptsAuthenticationException || $exception instanceof SecondFactorRequiredException) {
            return;
        }

        $summary = match ($event->getFirewallName()) {
            'backoffice' => $exception instanceof InvalidSecondFactorException ? 'Échec de connexion au backoffice (code de vérification invalide)' : 'Échec de connexion au backoffice',
            'clinic'     => 'Échec de connexion à la VetApp',
            default      => null,
        };

        if (null === $summary) {
            return;
        }

        $request   = $event->getRequest();
        $attempted = $request->attributes->get(SecurityRequestAttributes::LAST_USERNAME);
        $email     = \is_string($attempted) ? (string) preg_replace('/^[a-z]+:/', '', $attempted) : '';

        $this->record(
            kind: ActorKind::UNKNOWN,
            actorId: null,
            actorName: null,
            action: 'backoffice' === $event->getFirewallName() ? 'admin.login_failed' : 'auth.login_failed',
            summary: $summary,
            request: $request,
            details: '' !== $email ? ['E-mail saisi' => $email] : [],
        );
    }

    private function recordClinicSignIn(SecurityUser $user, Request $request): void
    {
        $clinicId = $this->currentClinicContext->getCurrentClinicId()?->toString();
        $clinic   = null !== $clinicId ? $this->queryBus->ask(new GetClinic($clinicId)) : null;
        $printed  = null !== $clinicId ? $this->queryBus->ask(new GetStaffPrintedName($clinicId, $user->id())) : null;

        $this->record(
            kind: ActorKind::STAFF,
            actorId: $user->id(),
            actorName: \is_string($printed) && '' !== $printed ? $printed : $user->getUserIdentifier(),
            action: 'auth.login',
            summary: 'Connexion à la VetApp',
            request: $request,
            clinicId: $clinic instanceof ClinicDto ? $clinic->id : null,
            clinicName: $clinic instanceof ClinicDto ? $clinic->name : null,
            targetUserId: $user->id(),
        );
    }

    /**
     * @param array<string, string> $details
     */
    private function record(
        ActorKind $kind,
        ?string $actorId,
        ?string $actorName,
        string $action,
        string $summary,
        Request $request,
        ?string $clinicId = null,
        ?string $clinicName = null,
        ?string $targetUserId = null,
        array $details = [],
    ): void {
        $this->commandBus->dispatch(new RecordAuditEntry(
            actorKind: $kind,
            actorId: $actorId,
            actorName: $actorName,
            action: $action,
            category: AuditCategory::ACCESS,
            summary: $summary,
            clinicId: $clinicId,
            clinicName: $clinicName,
            targetUserId: $targetUserId,
            ipAddress: $request->getClientIp(),
            details: ConsoleAuditTrail::details($details),
        ));
    }
}
