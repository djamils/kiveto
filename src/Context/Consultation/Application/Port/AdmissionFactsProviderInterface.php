<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Port;

/**
 * What the Admission context knows about the visits the consultations belong
 * to, for the register: which visits are over and which were emergencies.
 */
interface AdmissionFactsProviderInterface
{
    /**
     * The visits among these that are already closed (the patient has left).
     *
     * @param list<string> $admissionIds
     *
     * @return list<string>
     */
    public function closedAmong(string $clinicId, array $admissionIds): array;

    /**
     * The visits among these that came in as an emergency.
     *
     * @param list<string> $admissionIds
     *
     * @return list<string>
     */
    public function emergenciesAmong(string $clinicId, array $admissionIds): array;

    /**
     * Every emergency visit of the clinic, for the register's « Urgences » filter.
     *
     * @return list<string>
     */
    public function emergencyAdmissionIds(string $clinicId): array;
}
