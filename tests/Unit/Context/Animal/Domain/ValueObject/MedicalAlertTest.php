<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Animal\Domain\ValueObject;

use App\Context\Animal\Domain\ValueObject\MedicalAlert;
use App\Context\Animal\Domain\ValueObject\MedicalAlertKind;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class MedicalAlertTest extends TestCase
{
    public function testCreateGeneratesIdAndKeepsValues(): void
    {
        $alert = MedicalAlert::create(MedicalAlertKind::ALLERGY, 'Pénicilline', 'Choc anaphylactique en 2023');

        self::assertTrue(Uuid::isValid($alert->id));
        self::assertSame(MedicalAlertKind::ALLERGY, $alert->kind);
        self::assertSame('Pénicilline', $alert->label);
        self::assertSame('Choc anaphylactique en 2023', $alert->note);
    }

    public function testCreateTrimsLabelAndNote(): void
    {
        $alert = MedicalAlert::create(MedicalAlertKind::CHRONIC_CONDITION, "  Diabète  \n", "  Insuline matin  \n");

        self::assertSame('Diabète', $alert->label);
        self::assertSame('Insuline matin', $alert->note);
    }

    public function testCreateDefaultsNoteToNull(): void
    {
        $alert = MedicalAlert::create(MedicalAlertKind::CHRONIC_CONDITION, 'Insuffisance rénale');

        self::assertNull($alert->note);
    }

    public function testCreateTurnsBlankNoteIntoNull(): void
    {
        $alert = MedicalAlert::create(MedicalAlertKind::ALLERGY, 'Pollen', "   \n  ");

        self::assertNull($alert->note);
    }

    public function testCreateAcceptsLabelOfExactlyMaxLength(): void
    {
        $label = str_repeat('a', 120);

        $alert = MedicalAlert::create(MedicalAlertKind::ALLERGY, $label);

        self::assertSame($label, $alert->label);
    }

    public function testCreateAcceptsNoteOfExactlyMaxLength(): void
    {
        $note = str_repeat('n', 1000);

        $alert = MedicalAlert::create(MedicalAlertKind::ALLERGY, 'Pollen', $note);

        self::assertSame($note, $alert->note);
    }

    public function testCreateRejectsEmptyLabel(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Medical alert label cannot be empty');

        MedicalAlert::create(MedicalAlertKind::ALLERGY, '');
    }

    public function testCreateRejectsBlankLabel(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Medical alert label cannot be empty');

        MedicalAlert::create(MedicalAlertKind::ALLERGY, "   \t  ");
    }

    public function testCreateRejectsTooLongLabel(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Medical alert label cannot exceed 120 characters');

        MedicalAlert::create(MedicalAlertKind::ALLERGY, str_repeat('a', 121));
    }

    public function testCreateRejectsTooLongNote(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Medical alert note cannot exceed 1000 characters');

        MedicalAlert::create(MedicalAlertKind::ALLERGY, 'Pollen', str_repeat('n', 1001));
    }

    public function testReconstituteKeepsPersistedValuesVerbatim(): void
    {
        $alert = MedicalAlert::reconstitute(
            id: '01234567-89ab-cdef-0123-456789abcdef',
            kind: MedicalAlertKind::CHRONIC_CONDITION,
            label: 'Épilepsie',
            note: null,
        );

        self::assertSame('01234567-89ab-cdef-0123-456789abcdef', $alert->id);
        self::assertSame(MedicalAlertKind::CHRONIC_CONDITION, $alert->kind);
        self::assertSame('Épilepsie', $alert->label);
        self::assertNull($alert->note);
    }

    public function testIsAllergyIsTrueForAllergyKind(): void
    {
        $alert = MedicalAlert::create(MedicalAlertKind::ALLERGY, 'Pénicilline');

        self::assertTrue($alert->isAllergy());
    }

    public function testIsAllergyIsFalseForChronicCondition(): void
    {
        $alert = MedicalAlert::create(MedicalAlertKind::CHRONIC_CONDITION, 'Diabète');

        self::assertFalse($alert->isAllergy());
    }

    public function testMatchesSubstanceWhenSubstanceContainsLabel(): void
    {
        $alert = MedicalAlert::create(MedicalAlertKind::ALLERGY, 'amoxicilline');

        self::assertTrue($alert->matchesSubstance('amoxicilline trihydratée'));
    }

    public function testMatchesSubstanceIsCaseInsensitive(): void
    {
        $alert = MedicalAlert::create(MedicalAlertKind::ALLERGY, 'AmoxiCILLine');

        self::assertTrue($alert->matchesSubstance('AMOXICILLINE TRIHYDRATÉE'));
    }

    public function testMatchesSubstanceIsFalseWhenSubstanceIsUnrelated(): void
    {
        $alert = MedicalAlert::create(MedicalAlertKind::ALLERGY, 'amoxicilline');

        self::assertFalse($alert->matchesSubstance('méloxicam'));
    }

    public function testMatchesSubstanceIsFalseWhenLabelIsEmpty(): void
    {
        $alert = MedicalAlert::reconstitute(
            id: '01234567-89ab-cdef-0123-456789abcdef',
            kind: MedicalAlertKind::ALLERGY,
            label: '',
            note: null,
        );

        self::assertFalse($alert->matchesSubstance('méloxicam'));
    }

    public function testKindLabels(): void
    {
        self::assertSame('Allergie', MedicalAlertKind::ALLERGY->label());
        self::assertSame('Suivi', MedicalAlertKind::CHRONIC_CONDITION->label());
    }

    public function testKindValues(): void
    {
        self::assertSame('ALLERGY', MedicalAlertKind::ALLERGY->value);
        self::assertSame('CHRONIC_CONDITION', MedicalAlertKind::CHRONIC_CONDITION->value);
    }
}
