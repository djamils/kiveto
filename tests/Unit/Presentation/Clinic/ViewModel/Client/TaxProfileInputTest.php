<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Clinic\ViewModel\Client;

use App\Presentation\Clinic\ViewModel\Client\TaxProfileInput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TaxProfileInputTest extends TestCase
{
    public function testAFormWithoutTheBlockHasNoProfile(): void
    {
        self::assertNull(TaxProfileInput::fromArray([]));
        self::assertNull(TaxProfileInput::fromArray(['fisc' => 'b2b']));
    }

    public function testAnIndividualKeepsItsLegalNameAndDropsIdentifiers(): void
    {
        $input = TaxProfileInput::fromArray(['fisc' => 'ind', 'rs' => '  Refuge   des Collines ', 'siren' => '523456788', 'tva' => 'FR31523456788']);

        self::assertNotNull($input);
        self::assertFalse($input->isProfessional());
        self::assertSame([], $input->errors());
        self::assertNull($input->firstError());

        $dto = $input->toClientDto();
        self::assertSame('individual', $dto->status);
        self::assertSame('Refuge des Collines', $dto->legalName);
        self::assertNull($dto->siren);
        self::assertNull($dto->vatNumber);
    }

    public function testAStructureOnlyWindowAsksForTheLegalNameOfANonLiableStructure(): void
    {
        $input = TaxProfileInput::fromArray(['fisc' => 'ind', 'rs' => '  ']);

        self::assertNotNull($input);
        self::assertSame([], $input->errors());
        self::assertSame(['rs' => 'La raison sociale est obligatoire.'], $input->errors(structureOnly: true));
        self::assertSame('La raison sociale est obligatoire.', $input->firstError(structureOnly: true));

        $named = TaxProfileInput::fromArray(['fisc' => 'ind', 'rs' => 'Refuge des Collines']);
        self::assertNotNull($named);
        self::assertNull($named->firstError(structureOnly: true));
    }

    public function testATooLongLegalNameIsRefusedNotCut(): void
    {
        $longest = TaxProfileInput::fromArray(['fisc' => 'ind', 'rs' => str_repeat('é', 255)]);
        self::assertNotNull($longest);
        self::assertSame([], $longest->errors());
        self::assertSame(str_repeat('é', 255), $longest->legalName);

        $tooLong = TaxProfileInput::fromArray(['fisc' => 'pro', 'rs' => str_repeat('é', 256), 'siren' => '523456788']);
        self::assertNotNull($tooLong);
        self::assertSame(str_repeat('é', 256), $tooLong->legalName);
        self::assertSame(['rs' => 'La raison sociale ne doit pas dépasser 255 caractères.'], $tooLong->errors());
    }

    public function testAProfessionalIsNormalisedForBothContexts(): void
    {
        $input = TaxProfileInput::fromArray(['fisc' => 'pro', 'rs' => 'Élevage des Calanques', 'siren' => '523 456 788 00012', 'tva' => 'fr 31 523456788']);

        self::assertNotNull($input);
        self::assertTrue($input->isProfessional());

        $dto = $input->toClientDto();
        self::assertSame('french_business', $dto->status);
        self::assertSame('523456788', $dto->siren);
        self::assertSame('FR31523456788', $dto->vatNumber);

        $billing = $input->toBillingInput();
        self::assertSame(['french_business', 'Élevage des Calanques', '523456788', 'FR31523456788'], [$billing->status, $billing->legalName, $billing->siren, $billing->vatNumber]);
    }

    public function testAProfessionalWithoutVatNumberIsAccepted(): void
    {
        $input = TaxProfileInput::fromArray(['fisc' => 'pro', 'rs' => 'Centre équestre du Lac', 'siren' => '812345676']);

        self::assertNotNull($input);
        self::assertSame([], $input->errors());
        self::assertNull($input->toClientDto()->vatNumber);
    }

    /**
     * @return iterable<string, array{array<string, string>, array<string, string>}>
     */
    public static function provideEveryMistakeIsNamedInFrenchCases(): iterable
    {
        yield 'no legal name' => [['rs' => ' '], ['rs' => 'La raison sociale est obligatoire.']];
        yield 'short SIREN' => [['siren' => '52345678'], ['siren' => 'SIREN invalide : 9 chiffres attendus.']];
        yield 'bad SIREN key' => [['siren' => '523456789'], ['siren' => 'SIREN invalide : clé de contrôle incorrecte.']];
        yield 'VAT format' => [['tva' => 'FR3152345678'], ['tva' => 'N° de TVA invalide : format FR + 2 chiffres + SIREN.']];
        yield 'VAT of another SIREN' => [['tva' => 'FR31123456789'], ['tva' => 'N° de TVA : il ne correspond pas au SIREN.']];
        yield 'VAT key' => [['tva' => 'FR23523456788'], ['tva' => 'N° de TVA invalide : clé incorrecte.']];
    }

    /**
     * @param array<string, string> $override
     * @param array<string, string> $expected
     */
    #[DataProvider('provideEveryMistakeIsNamedInFrenchCases')]
    public function testEveryMistakeIsNamedInFrench(array $override, array $expected): void
    {
        $input = TaxProfileInput::fromArray([...['fisc' => 'pro', 'rs' => 'Calanques', 'siren' => '523456788', 'tva' => ''], ...$override]);

        self::assertNotNull($input);
        self::assertSame($expected, $input->errors());
        self::assertSame(array_values($expected)[0], $input->firstError());
    }

    public function testAnInvalidProfileIsSentAsTyped(): void
    {
        $input = TaxProfileInput::fromArray(['fisc' => 'pro', 'rs' => 'Calanques', 'siren' => '523456789', 'tva' => 'FR00']);

        self::assertNotNull($input);
        self::assertSame('523456789', $input->toClientDto()->siren);
        self::assertSame('FR00', $input->toClientDto()->vatNumber);
    }
}
