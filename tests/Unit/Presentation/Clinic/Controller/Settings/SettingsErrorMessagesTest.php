<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Clinic\Controller\Settings;

use App\Context\Clinic\Domain\Exception\InvalidLetterheadException;
use App\Context\Clinic\Domain\Exception\InvoicePrefixAlreadyUsedException;
use App\Presentation\Clinic\Controller\Settings\UpdateClinicBillingController;
use App\Presentation\Clinic\Controller\Settings\UpdateClinicIdentityController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What the settings page says, and on which field, when a save is refused.
 */
final class SettingsErrorMessagesTest extends TestCase
{
    /**
     * @return iterable<string, array{0: \LogicException, 1: array{0: string, 1: string|null}}>
     */
    public static function provideTheIdentityErrorIsShownOnItsFieldCases(): iterable
    {
        $siret = InvalidLetterheadException::FIELD_SIRET;
        $vat   = InvalidLetterheadException::FIELD_VAT_NUMBER;

        yield 'a SIRET of the wrong length' => [new InvalidLetterheadException($siret, InvalidLetterheadException::REASON_FORMAT, '812'), ['SIRET invalide : vérifiez les 14 chiffres.', 'id.siret']];
        yield 'a SIRET that fails its check digit' => [new InvalidLetterheadException($siret, InvalidLetterheadException::REASON_CHECKSUM, '81245690300027'), ['SIRET invalide : vérifiez les 14 chiffres.', 'id.siret']];
        yield 'a VAT number without a SIRET' => [new InvalidLetterheadException($siret, InvalidLetterheadException::REASON_MISSING, ''), ['Renseignez le SIRET pour enregistrer un n° de TVA.', 'id.siret']];
        yield 'a VAT number of the wrong shape' => [new InvalidLetterheadException($vat, InvalidLetterheadException::REASON_FORMAT, 'FR1'), ['N° de TVA invalide : format FR + 2 chiffres + SIREN.', 'id.tva']];
        yield 'a VAT number of another company' => [new InvalidLetterheadException($vat, InvalidLetterheadException::REASON_SIREN_MISMATCH, 'FR00901122333'), ['N° de TVA : il ne correspond pas au SIRET.', 'id.tva']];
        yield 'a VAT number with a wrong key' => [new InvalidLetterheadException($vat, InvalidLetterheadException::REASON_KEY, 'FR41812456903'), ['N° de TVA invalide : clé incorrecte.', 'id.tva']];
        yield 'a SIRET joining a company whose prefixes collide' => [new InvoicePrefixAlreadyUsedException('FAC', InvoicePrefixAlreadyUsedException::SERIES_INVOICE), ['Ce préfixe est déjà utilisé par un autre établissement du même SIREN.', 'id.siret']];
    }

    /**
     * @param array{0: string, 1: string|null} $expected
     */
    #[DataProvider('provideTheIdentityErrorIsShownOnItsFieldCases')]
    public function testTheIdentityErrorIsShownOnItsField(\LogicException $exception, array $expected): void
    {
        self::assertSame($expected, (new \ReflectionMethod(UpdateClinicIdentityController::class, 'explain'))->invoke(null, $exception));
    }

    public function testATakenPrefixIsShownOnThePrefixOfItsSeries(): void
    {
        $explain = new \ReflectionMethod(UpdateClinicBillingController::class, 'explain');
        $message = 'Ce préfixe est déjà utilisé par un autre établissement du même SIREN.';

        self::assertSame([$message, 'fa.prefixe'], $explain->invoke(null, new InvoicePrefixAlreadyUsedException('LYO', InvoicePrefixAlreadyUsedException::SERIES_INVOICE)));
        self::assertSame([$message, 'fa.av'], $explain->invoke(null, new InvoicePrefixAlreadyUsedException('AVLYO', InvoicePrefixAlreadyUsedException::SERIES_CREDIT_NOTE)));
    }
}
