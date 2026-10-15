<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

/**
 * The clinic changed how it looks: its colour in the clinic switcher, or the
 * logo it prints on its documents.
 */
readonly class ClinicBrandingChanged extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'clinic';
    protected const int    VERSION         = 1;

    public function __construct(
        private string $clinicId,
        private ?string $brandColor,
        private bool $hasLogo,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->clinicId;
    }

    public function payload(): array
    {
        return [
            'clinicId'   => $this->clinicId,
            'brandColor' => $this->brandColor,
            'hasLogo'    => $this->hasLogo,
        ];
    }
}
