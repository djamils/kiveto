<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Support;

use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Audit\Application\Command\RecordAuditEntry\RecordAuditEntry;
use App\System\Audit\Domain\ValueObject\ActorKind;
use App\System\Audit\Domain\ValueObject\AuditCategory;
use App\System\IdentityAccess\Application\Command\EndSupportSession\EndSupportSession;
use App\System\IdentityAccess\Application\Query\SupportSessionView;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Ends a support session on the clinic host (« Terminer la session », or
 * its time ran out): recorded, audited for the clinic, the browser signed
 * out and sent back to the clinic's sheet in the console (D3).
 */
final readonly class SupportSessionCloser
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private TokenStorageInterface $tokenStorage,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function close(Request $request, SupportSessionState $state): RedirectResponse
    {
        $answer = $this->commandBus->dispatch(new EndSupportSession($state->sessionId()));
        $view   = \is_array($answer) ? ($answer['session'] ?? null) : null;
        $ended  = \is_array($answer) && true === ($answer['ended'] ?? false);

        if ($view instanceof SupportSessionView && $ended) {
            // Ended at its expiry: its time ran out.
            $expired = null !== $view->endedAt && $view->endedAt >= $view->expiresAt;

            $this->commandBus->dispatch(new RecordAuditEntry(
                actorKind: ActorKind::KIVETO,
                actorId: $state->data['adminId'],
                actorName: $state->data['adminName'],
                action: 'support.session_ended',
                category: AuditCategory::SUPPORT,
                summary: \sprintf('Session support terminée%s · %d min', $expired ? ' (temps écoulé)' : '', $view->minutes),
                clinicId: $state->data['clinicId'],
                clinicName: $state->data['clinicName'],
                targetUserId: $view->userId,
                ipAddress: $request->getClientIp(),
                details: [
                    ['label' => 'En tant que', 'value' => $state->data['userName']],
                    ['label' => 'Mode', 'value' => $view->modeLabel],
                    ['label' => 'Pages consultées', 'value' => (string) $view->pages],
                    ['label' => 'Modifications', 'value' => (string) $view->writes],
                ],
            ));
        }

        $this->tokenStorage->setToken(null);
        $request->getSession()->invalidate();

        return new RedirectResponse($this->urls->generate('backoffice_clinic_show', ['id' => $state->data['clinicId'], 'support_ended' => $state->sessionId()], UrlGeneratorInterface::ABSOLUTE_URL));
    }
}
