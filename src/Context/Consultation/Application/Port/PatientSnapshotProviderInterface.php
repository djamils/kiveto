<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Port;

/**
 * The animal seen and its owner, as the consultation report prints them. A
 * patient not reconciled with an animal yet still has a snapshot (its
 * provisional label and observed species), with no owner.
 */
interface PatientSnapshotProviderInterface
{
    public function snapshot(string $clinicId, string $patientId): ReportPatientSnapshot;
}
