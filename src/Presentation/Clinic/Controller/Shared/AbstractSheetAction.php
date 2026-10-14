<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Shared;

use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

/**
 * What every form of a sheet (client, animal) shares: a CSRF check, the
 * current clinic, and a way to run a domain action without leaking its
 * developer message to the page.
 */
abstract class AbstractSheetAction extends AbstractController
{
    /** The CSRF token id shared by every form of the sheet. */
    protected const string CSRF_ID = 'sheet';

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
}
