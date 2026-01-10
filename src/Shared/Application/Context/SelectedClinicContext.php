<?php

declare(strict_types=1);

namespace App\Shared\Application\Context;

use App\Clinic\Domain\ValueObject\ClinicId;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class SelectedClinicContext
{
    private const string SESSION_KEY = 'selected_clinic_id';

    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function setSelectedClinicId(ClinicId $clinicId): void
    {
        $session = $this->requestStack->getSession();
        $session->set(self::SESSION_KEY, $clinicId->toString());
    }

    public function getSelectedClinicId(): ?ClinicId
    {
        $session     = $this->requestStack->getSession();
        $clinicIdStr = $session->get(self::SESSION_KEY);

        if (null === $clinicIdStr || !\is_string($clinicIdStr)) {
            return null;
        }

        return ClinicId::fromString($clinicIdStr);
    }

    public function hasSelectedClinic(): bool
    {
        return null !== $this->getSelectedClinicId();
    }

    public function clearSelectedClinic(): void
    {
        $session = $this->requestStack->getSession();
        $session->remove(self::SESSION_KEY);
    }
}
