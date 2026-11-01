<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Domain\Request;

use App\Context\ClientPortal\Domain\Exception\InvalidPortalRequestException;
use App\Context\ClientPortal\Domain\Request\ChangeField;
use App\Context\ClientPortal\Domain\Request\ProposedChange;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** S6e, AC 235: the validation table of a pet change. */
final class ProposedChangeTest extends TestCase
{
    /**
     * @return iterable<string, array{ChangeField, string, ?string, ?string, array<string, string|int>}>
     */
    public static function provideAValidChangeIsNormalisedCases(): iterable
    {
        yield 'name trimmed' => [ChangeField::NAME, '  Moka  ', null, null, ['name' => 'Moka']];
        yield 'name of 60' => [ChangeField::NAME, str_repeat('é', 60), null, null, ['name' => str_repeat('é', 60)]];
        yield 'weight with a comma' => [ChangeField::WEIGHT, '12,4', null, null, ['weightGrams' => 12400, 'measuredOn' => '2026-10-29']];
        yield 'weight with a dot, measured earlier' => [ChangeField::WEIGHT, '0.05', '2026-10-20', null, ['weightGrams' => 50, 'measuredOn' => '2026-10-20']];
        yield 'weight of 150 kg measured today' => [ChangeField::WEIGHT, '150', '2026-10-29', null, ['weightGrams' => 150000, 'measuredOn' => '2026-10-29']];
        yield 'neutered' => [ChangeField::STERILIZATION, 'neutered', null, null, ['reproductiveStatus' => 'neutered']];
        yield 'intact' => [ChangeField::STERILIZATION, 'intact', null, null, ['reproductiveStatus' => 'intact']];
        yield 'chip' => [ChangeField::CHIP_NUMBER, '250269604123456', null, null, ['chipNumber' => '250269604123456']];
        yield 'other with a note, no value' => [ChangeField::OTHER, '', null, ' Nouvelle adresse du propriétaire ', []];
    }

    /**
     * @param array<string, string|int> $normalised
     */
    #[DataProvider('provideAValidChangeIsNormalisedCases')]
    public function testAValidChangeIsNormalised(ChangeField $field, string $raw, ?string $measuredOn, ?string $note, array $normalised): void
    {
        $change = ProposedChange::of($field, $raw, null === $measuredOn ? null : new \DateTimeImmutable($measuredOn), $note, self::today());

        self::assertSame([$field, trim($raw), $normalised, null === $note ? null : trim($note)], [$change->field(), $change->proposedValue(), $change->normalised(), $change->note()]);
    }

    /**
     * @return iterable<string, array{ChangeField, string, ?string, ?string, string}>
     */
    public static function provideAnInvalidChangeIsRefusedOnItsPropertyCases(): iterable
    {
        yield 'empty name' => [ChangeField::NAME, '   ', null, null, 'proposedValue'];
        yield 'name over 60' => [ChangeField::NAME, str_repeat('a', 61), null, null, 'proposedValue'];
        yield 'value over 120' => [ChangeField::OTHER, str_repeat('a', 121), null, 'note', 'proposedValue'];
        yield 'weight not a number' => [ChangeField::WEIGHT, 'douze', null, null, 'proposedValue'];
        yield 'weight with 4 decimals' => [ChangeField::WEIGHT, '12,4567', null, null, 'proposedValue'];
        yield 'weight below 0.05 kg' => [ChangeField::WEIGHT, '0,04', null, null, 'proposedValue'];
        yield 'weight above 150 kg' => [ChangeField::WEIGHT, '150,001', null, null, 'proposedValue'];
        yield 'weight measured tomorrow' => [ChangeField::WEIGHT, '12', '2026-10-30', null, 'measuredOn'];
        yield 'sterilization unknown' => [ChangeField::STERILIZATION, 'unknown', null, null, 'proposedValue'];
        yield 'chip of 14 digits' => [ChangeField::CHIP_NUMBER, '25026960412345', null, null, 'proposedValue'];
        yield 'chip with letters' => [ChangeField::CHIP_NUMBER, '25026960412345A', null, null, 'proposedValue'];
        yield 'other without note' => [ChangeField::OTHER, 'Adresse', null, '  ', 'note'];
        yield 'note over 500' => [ChangeField::NAME, 'Moka', null, str_repeat('a', 501), 'note'];
    }

    #[DataProvider('provideAnInvalidChangeIsRefusedOnItsPropertyCases')]
    public function testAnInvalidChangeIsRefusedOnItsProperty(ChangeField $field, string $raw, ?string $measuredOn, ?string $note, string $property): void
    {
        try {
            ProposedChange::of($field, $raw, null === $measuredOn ? null : new \DateTimeImmutable($measuredOn), $note, self::today());
            self::fail('The change should be refused.');
        } catch (InvalidPortalRequestException $e) {
            self::assertSame($property, $e->propertyPath);
            self::assertNotSame('', $e->getMessage());
        }
    }

    public function testTheFreeTextIsErasedWithoutTouchingTheNormalisedValue(): void
    {
        $other = ProposedChange::of(ChangeField::OTHER, 'Nouvelle adresse', null, 'Rue des Lilas', self::today())->withoutFreeText();
        $name  = ProposedChange::of(ChangeField::NAME, 'Moka', null, 'Faute de frappe', self::today())->withoutFreeText();

        self::assertSame(['', null, []], [$other->proposedValue(), $other->note(), $other->normalised()]);
        self::assertSame(['Moka', null, ['name' => 'Moka']], [$name->proposedValue(), $name->note(), $name->normalised()]);
    }

    public function testAStoredChangeIsReconstitutedAsIs(): void
    {
        $change = ProposedChange::reconstitute(ChangeField::WEIGHT, '12,4', ['weightGrams' => 12400, 'measuredOn' => '2026-10-29'], null);

        self::assertSame([ChangeField::WEIGHT, '12,4', ['weightGrams' => 12400, 'measuredOn' => '2026-10-29'], null], [$change->field(), $change->proposedValue(), $change->normalised(), $change->note()]);
    }

    private static function today(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-29 23:30:00', new \DateTimeZone('Europe/Paris'));
    }
}
