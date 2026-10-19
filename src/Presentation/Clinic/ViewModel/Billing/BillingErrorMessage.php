<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Billing;

use App\Context\Billing\Domain\Exception\CounterClientAlreadyKnownException;
use App\Context\Billing\Domain\Exception\CreditExceedsInvoiceException;
use App\Context\Billing\Domain\Exception\InvalidCashClosingException;
use App\Context\Billing\Domain\Exception\InvalidChequeDepositException;
use App\Context\Billing\Domain\Exception\InvalidCounterClientException;
use App\Context\Billing\Domain\Exception\InvalidCounterClientTaxProfileException;
use App\Context\Billing\Domain\Exception\InvalidCreditNoteException;
use App\Context\Billing\Domain\Exception\InvalidInvoiceLineException;
use App\Context\Billing\Domain\Exception\InvalidPayerException;
use App\Context\Billing\Domain\Exception\InvalidPaymentException;
use App\Context\Billing\Domain\Exception\InvoiceHasNoLinesException;
use App\Context\Billing\Domain\Exception\InvoiceNotCreditableException;
use App\Context\Billing\Domain\Exception\InvoiceNotEditableException;
use App\Context\Billing\Domain\Exception\InvoiceNotFoundException;
use App\Context\Billing\Domain\Exception\InvoiceNotPayableException;
use App\Context\Billing\Domain\Exception\InvoiceRequiresIdentifiedPayerException;
use App\Context\Billing\Domain\Exception\IssuedProfessionalInvoiceCannotBeCancelledException;
use App\Context\Billing\Domain\Exception\PaymentExceedsBalanceException;

/**
 * The desk's words for what the Billing context refused: its exceptions speak
 * English to developers, the toast speaks French to the receptionist.
 */
final class BillingErrorMessage
{
    private const array MESSAGES = [
        PaymentExceedsBalanceException::class                      => 'Le montant dépasse ce qui reste à régler.',
        InvoiceNotPayableException::class                          => 'Cette facture n\'attend plus de règlement.',
        InvoiceNotCreditableException::class                       => 'Un avoir ne s\'établit que sur une facture émise.',
        CreditExceedsInvoiceException::class                       => 'L\'avoir dépasse ce qui reste à créditer sur la facture.',
        InvalidCreditNoteException::class                          => 'L\'avoir n\'a pas pu être établi : choisissez des lignes non créditées et un motif.',
        InvoiceHasNoLinesException::class                          => 'La facture n\'a aucune ligne à encaisser.',
        InvoiceRequiresIdentifiedPayerException::class             => 'Identifiez le patient avant d\'encaisser : la facture doit être au nom d\'un client.',
        InvoiceNotEditableException::class                         => 'La facture est déjà émise : elle ne se modifie plus.',
        IssuedProfessionalInvoiceCannotBeCancelledException::class => 'Une facture professionnelle émise ne s\'annule pas : créez un avoir.',
        InvoiceNotFoundException::class                            => 'Facture introuvable.',
        InvalidInvoiceLineException::class                         => 'Un article ne peut pas être vendu au comptoir.',
        InvalidPaymentException::class                             => 'Le montant du règlement est invalide.',
        InvalidChequeDepositException::class                       => 'Aucun chèque à remettre en banque.',
        InvalidPayerException::class                               => 'Choisissez le client de la vente.',
        CounterClientAlreadyKnownException::class                  => 'Ce numéro est déjà celui d\'un client : utilisez sa fiche.',
        InvalidCounterClientException::class                       => 'Les coordonnées du nouveau client sont incomplètes : un nom, un prénom et un mobile ou un e-mail valides.',
        InvalidCounterClientTaxProfileException::class             => 'Le profil fiscal du professionnel est invalide : vérifiez la raison sociale, le SIREN et le n° de TVA.',
    ];

    public static function of(\Throwable $exception): string
    {
        if ($exception instanceof InvalidCashClosingException) {
            return str_contains($exception->getMessage(), 'already closed')
                ? 'La journée est déjà clôturée.'
                : 'Un écart entre la caisse et les encaissements demande un commentaire.';
        }

        if ($exception instanceof \ValueError) {
            return 'Moyen de paiement inconnu.';
        }

        foreach (self::MESSAGES as $class => $message) {
            if ($exception instanceof $class) {
                return $message;
            }
        }

        return 'L\'opération n\'a pas pu être enregistrée.';
    }
}
