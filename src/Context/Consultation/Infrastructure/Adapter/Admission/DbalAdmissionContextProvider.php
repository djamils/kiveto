<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Adapter\Admission;

use App\Context\Consultation\Application\Port\AdmissionContextDto;
use App\Context\Consultation\Application\Port\AdmissionContextProviderInterface;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DbalAdmissionContextProvider implements AdmissionContextProviderInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function getAdmissionContext(string $admissionId): AdmissionContextDto
    {
        $admissionBinary = Uuid::fromString($admissionId)->toBinary();

        $sql = '
            SELECT patient_id, clinic_id
            FROM admission__admissions
            WHERE id = :admissionId
        ';

        $result = $this->connection->fetchAssociative($sql, [
            'admissionId' => $admissionBinary,
        ]);

        if (false === $result) {
            throw new \DomainException('Admission not found');
        }

        return new AdmissionContextDto(
            patientId: RowAccessor::uuid($result, 'patient_id'),
            clinicId: RowAccessor::uuid($result, 'clinic_id'),
        );
    }
}
