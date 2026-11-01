<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Support;

use App\Context\ClientPortal\Domain\Request\ChangeField;
use App\Context\ClientPortal\Domain\Request\PetChangeRequest;
use App\Context\ClientPortal\Domain\Request\PetCondition;
use App\Context\ClientPortal\Domain\Request\PortalRequestId;
use App\Context\ClientPortal\Domain\Request\PrescriptionRenewalRequest;
use App\Context\ClientPortal\Domain\Request\PreVisitQuestionnaire;
use App\Context\ClientPortal\Domain\Request\ProposedChange;
use App\Context\ClientPortal\Domain\Request\QuestionnaireAnswers;

/** Submitted requests of the Pet world (clinic, client, account), in UTC. */
final class Requests
{
    public static function utc(string $at): \DateTimeImmutable
    {
        return new \DateTimeImmutable($at, new \DateTimeZone('UTC'));
    }

    public static function answers(string $temperament = 'calm', ?string $notes = 'Il tousse la nuit.'): QuestionnaireAnswers
    {
        return QuestionnaireAnswers::of(['cough'], true, 'Sirop', $temperament, $notes);
    }

    public static function questionnaire(
        string $id,
        string $appointmentId,
        string $at = '2026-10-28 08:00:00',
        string $userId = PetWorld::USER,
        string $clientId = PetWorld::CLIENT,
        string $clinicId = PetWorld::CLINIC,
        ?QuestionnaireAnswers $answers = null,
    ): PreVisitQuestionnaire {
        return PreVisitQuestionnaire::submit(PortalRequestId::fromString($id), $clinicId, $clientId, $userId, $appointmentId, 1, $answers ?? self::answers(), self::utc($at));
    }

    public static function change(
        string $id,
        string $petId = PetWorld::PET,
        ChangeField $field = ChangeField::WEIGHT,
        string $value = '12,4',
        string $at = '2026-10-28 08:00:00',
        string $userId = PetWorld::USER,
        string $clientId = PetWorld::CLIENT,
        string $clinicId = PetWorld::CLINIC,
        ?string $note = 'Pesé à la maison',
        ?string $current = '11800',
    ): PetChangeRequest {
        $submittedAt = self::utc($at);

        return PetChangeRequest::submit(
            PortalRequestId::fromString($id),
            $clinicId,
            $clientId,
            $userId,
            $petId,
            ProposedChange::of($field, $value, null, $note, $submittedAt),
            $current,
            $submittedAt,
        );
    }

    public static function renewal(
        string $id,
        string $prescriptionId,
        string $petId = PetWorld::PET,
        string $at = '2026-10-28 08:00:00',
        string $userId = PetWorld::USER,
        string $clientId = PetWorld::CLIENT,
        string $clinicId = PetWorld::CLINIC,
        PetCondition $condition = PetCondition::IMPROVED,
        ?string $note = 'Va mieux',
    ): PrescriptionRenewalRequest {
        return PrescriptionRenewalRequest::submit(PortalRequestId::fromString($id), $clinicId, $clientId, $userId, $prescriptionId, $petId, $condition, $note, self::utc($at));
    }
}
