<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Shared;

use App\Shared\Application\Context\CurrentClinicContextInterface;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * What every form of a sheet (client, animal) shares: a CSRF check, the
 * current clinic, and a way to run a domain action without leaking its
 * developer message to the page.
 */
abstract class AbstractSheetAction extends AbstractController
{
    /** The CSRF token id shared by every form of the sheet. */
    protected const string CSRF_ID = 'sheet';

    // Set on the request once a save lost a race: the caller's own failure message is then not shown.
    private const string CONFLICT = '_sheet_save_conflict';

    public static function getSubscribedServices(): array
    {
        return [...parent::getSubscribedServices(), CurrentClinicContextInterface::class];
    }

    protected function guard(Request $request): void
    {
        if (!$this->isCsrfTokenValid(static::CSRF_ID, $request->request->getString('_token'))) {
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

    /**
     * Runs a domain action and tells whether the domain refused it. The reason
     * is a developer message in English: callers show their own French text.
     * A save that lost a race to a concurrent one (another user, a background
     * verification) is refused too, with its own message.
     *
     * @param \Closure(): mixed $action
     */
    protected function attempt(\Closure $action): bool
    {
        try {
            $action();
        } catch (OptimisticLockException) {
            $this->addFlash('error', 'La fiche vient d’être mise à jour, réessayez.');
            $this->currentRequest()?->attributes->set(self::CONFLICT, true);

            return false;
        } catch (\DomainException) {
            return false;
        } catch (\InvalidArgumentException) {
            return false;
        } catch (\ValueError) {
            return false;
        }

        return true;
    }

    protected function addFlash(string $type, mixed $message): void
    {
        if ('error' === $type && true === $this->currentRequest()?->attributes->get(self::CONFLICT)) {
            return;
        }

        parent::addFlash($type, $message);
    }

    /**
     * A real calendar day in Y-m-d form: "2026-13-45" has the shape but is not a date.
     */
    protected function isDate(?string $value): bool
    {
        if (null === $value) {
            return false;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return false !== $date && $date->format('Y-m-d') === $value;
    }

    protected function trimmed(Request $request, string $field): ?string
    {
        $value = mb_trim($request->request->getString($field));

        return '' === $value ? null : $value;
    }

    protected function currentRequest(): ?Request
    {
        $requestStack = $this->container->get('request_stack');
        \assert($requestStack instanceof RequestStack);

        return $requestStack->getCurrentRequest();
    }
}
