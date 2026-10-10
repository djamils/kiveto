<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Client\Sheet;

use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * What every form of the client sheet shares: one CSRF token, the current
 * clinic, and the way back to the tab the form was submitted from.
 */
abstract class AbstractClientSheetAction extends AbstractController
{
    public const string CSRF_ID = 'client_sheet';

    public static function getSubscribedServices(): array
    {
        return [...parent::getSubscribedServices(), CurrentClinicContextInterface::class];
    }

    protected function guard(Request $request): void
    {
        if (!$this->isCsrfTokenValid(self::CSRF_ID, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }

    protected function clinicId(): string
    {
        $context = $this->container->get(CurrentClinicContextInterface::class);
        \assert($context instanceof CurrentClinicContextInterface);

        $clinicId = $context->getCurrentClinicId();
        \assert(null !== $clinicId);

        return $clinicId->toString();
    }

    protected function backToSheet(string $clientId, string $tab = 'synthese'): RedirectResponse
    {
        return $this->redirectToRoute(
            'clinic_clients_view',
            'synthese' === $tab ? ['id' => $clientId] : ['id' => $clientId, 'tab' => $tab],
            RedirectResponse::HTTP_SEE_OTHER,
        );
    }

    /**
     * Runs a domain action and tells whether the domain refused it. The reason
     * is a developer message in English: callers show their own French text.
     *
     * @param \Closure(): mixed $action
     */
    protected function attempt(\Closure $action): bool
    {
        try {
            $action();
        } catch (\DomainException) {
            return false;
        } catch (\InvalidArgumentException) {
            return false;
        } catch (\ValueError) {
            return false;
        }

        return true;
    }

    protected function trimmed(Request $request, string $field): ?string
    {
        $value = mb_trim($request->request->getString($field));

        return '' === $value ? null : $value;
    }
}
