<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Agenda;

use App\Context\Scheduling\Application\Query\GetAppointmentClinicId\GetAppointmentClinicId;
use App\Context\Scheduling\Domain\Exception\AppointmentAlreadyHandledException;
use App\Context\Scheduling\Domain\Exception\AppointmentNotModifiableException;
use App\Context\Scheduling\Domain\Exception\NewClientRequestException;
use App\Context\Scheduling\Domain\Exception\PendingAppointmentExpiredException;
use App\Context\Scheduling\Domain\Exception\SlotTakenException;
use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * What « Confirmer » and « Refuser » of an online request share. The agenda
 * posts them in the background and reads JSON (`{success, message}` or the
 * sibling endpoints' `{success: false, errors, errorCode}`); the dashboard
 * posts a form and is sent back with a flash. Both carry the `appointment`
 * token, need `manage_appointments`, and a request the clinic or the expiry
 * handled first answers its French reason, never a 500 (AC 144, 145,
 * S4A-06: any other domain refusal is a 409 too; an id that is no UUID is
 * a 404).
 */
final readonly class PendingRequestEndpoint
{
    public function __construct(
        private ClinicPermissionGuard $guard,
        private CsrfTokenManagerInterface $csrf,
        private CurrentClinicContextInterface $currentClinicContext,
        private QueryBusInterface $queryBus,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /**
     * @param \Closure(string $clinicId): mixed $decide dispatches the decision
     * @param string                            $done   the French message once decided
     */
    public function handle(Request $request, string $appointmentId, \Closure $decide, string $done): Response
    {
        if (!$this->csrf->isTokenValid(new CsrfToken('appointment', $request->request->getString('_token')))) {
            return $this->refuse($request, 'Session expirée : rechargez la page.', 'CSRF_INVALID', Response::HTTP_FORBIDDEN);
        }

        if (!$this->guard->has(ClinicPermissionGuard::MANAGE_APPOINTMENTS)) {
            return $this->refuse($request, 'Vous n’avez pas le droit de traiter les demandes de rendez-vous.', 'FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $clinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $clinicId);

        // Multi-tenant guard: another clinic's appointment (or no appointment id at all) does not exist here.
        if (!Uuid::isValid($appointmentId) || $this->queryBus->ask(new GetAppointmentClinicId($appointmentId)) !== $clinicId->toString()) {
            throw new NotFoundHttpException();
        }

        try {
            $decide($clinicId->toString());
        } catch (PendingAppointmentExpiredException $e) {
            return $this->refuse($request, $e->getMessage(), 'PENDING_EXPIRED', Response::HTTP_CONFLICT);
        } catch (AppointmentAlreadyHandledException $e) {
            return $this->refuse($request, $e->getMessage(), 'APPOINTMENT_ALREADY_HANDLED', Response::HTTP_CONFLICT);
        } catch (NewClientRequestException $e) {
            // A new client's request: the file and the animal are chosen in « Demandes Portal » (S7d)
            return $this->refuse($request, $e->getMessage(), 'NEW_CLIENT_REQUEST', Response::HTTP_CONFLICT, $this->urls->generate('clinic_portal_requests', ['type' => 'booking_request']));
        } catch (\InvalidArgumentException) {
            throw new NotFoundHttpException();
        } catch (SlotTakenException) {
            // The practitioner's agenda changed concurrently (lock contended): try again
            return $this->refuse($request, 'L’agenda est en cours de modification : réessayez dans un instant.', 'APPOINTMENT_CONFLICT', Response::HTTP_CONFLICT);
        } catch (AppointmentNotModifiableException $e) {
            return $this->refuse($request, $e->getMessage(), 'APPOINTMENT_CONFLICT', Response::HTTP_CONFLICT);
        } catch (\DomainException) {
            // Any other refusal is a conflict, never a 500; its message is not meant for staff
            return $this->refuse($request, 'Cette demande n’a pas pu être traitée : rechargez la page.', 'APPOINTMENT_CONFLICT', Response::HTTP_CONFLICT);
        }

        if (self::wantsJson($request)) {
            return new JsonResponse(['success' => true, 'appointmentId' => $appointmentId, 'message' => $done]);
        }

        $this->flash($request, 'success', $done);

        return new RedirectResponse($this->urls->generate('clinic_dashboard'));
    }

    private function refuse(Request $request, string $reason, string $code, int $status, ?string $redirect = null): Response
    {
        if (self::wantsJson($request)) {
            return new JsonResponse(array_filter(['success' => false, 'errors' => ['global' => [$reason]], 'errorCode' => $code, 'message' => $reason, 'redirect' => $redirect], static fn (mixed $value): bool => null !== $value), $status);
        }

        $this->flash($request, 'error', $reason);

        return new RedirectResponse($redirect ?? $this->urls->generate('clinic_dashboard'));
    }

    private function flash(Request $request, string $type, string $message): void
    {
        $session = $request->getSession();

        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add($type, $message);
        }
    }

    private static function wantsJson(Request $request): bool
    {
        return $request->isXmlHttpRequest() || 'json' === $request->getPreferredFormat();
    }
}
