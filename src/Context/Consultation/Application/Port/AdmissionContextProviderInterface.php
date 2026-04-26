<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Port;

interface AdmissionContextProviderInterface
{
    public function getAdmissionContext(string $admissionId): AdmissionContextDto;
}
