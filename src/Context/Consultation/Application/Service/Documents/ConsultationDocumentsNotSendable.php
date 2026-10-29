<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Service\Documents;

/**
 * Why the documents of a consultation cannot be e-mailed; the message is
 * shown to the user as is.
 */
final class ConsultationDocumentsNotSendable extends \DomainException
{
    public static function notClosed(): self
    {
        return new self('Clôturez la consultation avant d\'envoyer ses documents.');
    }

    public static function noEmail(): self
    {
        return new self('Aucune adresse e-mail pour ce client.');
    }

    public static function nothingSelected(): self
    {
        return new self('Sélectionnez au moins un document à envoyer.');
    }

    public static function foreignDocument(): self
    {
        return new self('Ce document n\'appartient pas à la consultation.');
    }
}
