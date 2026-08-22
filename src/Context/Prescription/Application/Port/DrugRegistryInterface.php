<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Port;

interface DrugRegistryInterface
{
    /**
     * What has to be printed about a medicine: its pharmaceutical form, its
     * active substances and how tightly it is controlled.
     */
    public function detailsOf(string $articleId, string $clinicId): DrugDetailsDto;
}
