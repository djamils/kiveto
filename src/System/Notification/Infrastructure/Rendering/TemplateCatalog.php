<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Rendering;

use App\System\Notification\Application\Exception\InvalidNotificationRequestException;
use App\System\Notification\Domain\Message\Channel;
use App\System\Notification\Domain\Message\MessagePurpose;

/**
 * The template of each purpose and channel and the exact parameters it
 * prints. Parameters are scalars, or lists of scalars for the names listed
 * as such; every value is ready to print (labels, amounts and dates are
 * formatted by the requester, in French).
 *
 * E-mail templates: notification/email/<name>.html.twig (subject in its
 * `subject` block) and notification/email/<name>.txt.twig; SMS templates:
 * notification/sms/<name>.txt.twig.
 */
final class TemplateCatalog
{
    private const string SCALAR = 'scalar';
    private const string LIST   = 'list';

    /**
     * @var array<string, array<string, array<string, self::SCALAR|self::LIST>>> purpose → channel → parameter → kind
     */
    private const array PARAMETERS = [
        'APPOINTMENT_CONFIRMATION' => [
            // addressee « Madame Dubois »; subjectDateLabel « jeudi 22 octobre à 9h30 »; dateLabel « Jeudi 22 octobre 2026 »;
            // timeLabel « 9h30 · 30 min »; reason « Vaccination »; practitionerName « Dr Rousseau » or '' (line omitted)
            'EMAIL' => ['addressee' => self::SCALAR, 'animalName' => self::SCALAR, 'subjectDateLabel' => self::SCALAR, 'dateLabel' => self::SCALAR, 'timeLabel' => self::SCALAR, 'reason' => self::SCALAR, 'practitionerName' => self::SCALAR],
            // slotLabel « jeudi 22/10 à 9h30 »
            'SMS' => ['animalName' => self::SCALAR, 'slotLabel' => self::SCALAR, 'practitionerName' => self::SCALAR],
        ],
        'APPOINTMENT_CHANGE' => [
            // previousSlotLabel « Jeu. 22 oct. · 9h30 », newSlotLabel « Ven. 23 oct. · 11h00 »; subjectDateLabel of the new slot
            'EMAIL' => ['addressee' => self::SCALAR, 'animalName' => self::SCALAR, 'subjectDateLabel' => self::SCALAR, 'previousSlotLabel' => self::SCALAR, 'newSlotLabel' => self::SCALAR, 'reason' => self::SCALAR, 'practitionerName' => self::SCALAR],
            // slotLabel « vendredi 23/10 à 11h00 », previousSlotLabel « jeudi 22/10 à 9h30 »
            'SMS' => ['animalName' => self::SCALAR, 'slotLabel' => self::SCALAR, 'previousSlotLabel' => self::SCALAR, 'practitionerName' => self::SCALAR],
        ],
        'APPOINTMENT_CANCELLATION' => [
            // timeLabel « 9h30 »
            'EMAIL' => ['addressee' => self::SCALAR, 'animalName' => self::SCALAR, 'subjectDateLabel' => self::SCALAR, 'dateLabel' => self::SCALAR, 'timeLabel' => self::SCALAR, 'reason' => self::SCALAR, 'practitionerName' => self::SCALAR],
            'SMS'   => ['animalName' => self::SCALAR, 'slotLabel' => self::SCALAR],
        ],
        'APPOINTMENT_REMINDER' => [
            // whenLabel « demain à 9h30 », « jeudi 22/10 à 9h30 » or « aujourd'hui à 9h30 »
            'SMS'   => ['animalName' => self::SCALAR, 'whenLabel' => self::SCALAR, 'practitionerName' => self::SCALAR],
            'EMAIL' => ['addressee' => self::SCALAR, 'animalName' => self::SCALAR, 'whenLabel' => self::SCALAR, 'practitionerName' => self::SCALAR],
        ],
        'INVOICE_SENT' => [
            // purchaseLabel « la consultation de Minou du 16 octobre 2026 » / « vos achats du 16/10/2026 »; invoiceDate « 16/10/2026 »;
            // vatLabels [« 20 % »] and vatAmounts [« 14,78 € »] side by side; total « 88,70 € »; balanceDue « 88,70 € » or '' when settled
            'EMAIL' => ['addressee' => self::SCALAR, 'invoiceNumber' => self::SCALAR, 'invoiceDate' => self::SCALAR, 'purchaseLabel' => self::SCALAR, 'vatLabels' => self::LIST, 'vatAmounts' => self::LIST, 'total' => self::SCALAR, 'balanceDue' => self::SCALAR],
        ],
        'INVOICE_REMINDER' => [
            // careLabel « des soins de Cookie »; invoiceDate « 04/09/2026 »; total, paid, balanceDue « 64,50 € »
            'EMAIL' => ['addressee' => self::SCALAR, 'invoiceNumber' => self::SCALAR, 'invoiceDate' => self::SCALAR, 'careLabel' => self::SCALAR, 'total' => self::SCALAR, 'paid' => self::SCALAR, 'balanceDue' => self::SCALAR],
            // shortDate « 04/09 »
            'SMS' => ['invoiceNumber' => self::SCALAR, 'shortDate' => self::SCALAR, 'balanceDue' => self::SCALAR],
        ],
        'CONSULTATION_DOCUMENTS' => [
            // consultationDateLabel « 16 octobre 2026 »; consultationLabel « 16/10/2026 · Dr Lambert »;
            // documentLabels [« Compte rendu », « Ordonnance », « Facture »] and documentValues [« Joint », « ORD-2026-0391 », « FA-2026-0814 · 88,70 € »]
            'EMAIL' => ['addressee' => self::SCALAR, 'animalName' => self::SCALAR, 'consultationDateLabel' => self::SCALAR, 'consultationLabel' => self::SCALAR, 'documentLabels' => self::LIST, 'documentValues' => self::LIST],
        ],
        'STAFF_INVITATION' => [
            // firstName « Hugo » or ''; inviterName « Dr Claire Lambert »; roleLabel « Vétérinaire »; activationUrl absolute
            'EMAIL' => ['firstName' => self::SCALAR, 'inviterName' => self::SCALAR, 'clinicName' => self::SCALAR, 'clinicCity' => self::SCALAR, 'roleLabel' => self::SCALAR, 'activationUrl' => self::SCALAR],
        ],
        'PASSWORD_RESET' => [
            // requestedAtLabel « 16/10/2026 à 08:14 » (Europe/Paris)
            'EMAIL' => ['accountEmail' => self::SCALAR, 'resetUrl' => self::SCALAR, 'requestedAtLabel' => self::SCALAR],
        ],
        'TEST' => [
            // sentAtLabel « 26/10/2026 à 10:42 »
            'EMAIL' => ['sentAtLabel' => self::SCALAR],
            'SMS'   => ['sentAtLabel' => self::SCALAR],
        ],
    ];

    public static function supports(MessagePurpose $purpose, Channel $channel): bool
    {
        return isset(self::PARAMETERS[$purpose->value][$channel->value]);
    }

    /** The template name without folder nor extension. */
    public static function templateName(MessagePurpose $purpose): string
    {
        return mb_strtolower($purpose->value);
    }

    /**
     * @return array<string, 'scalar'|'list'>
     */
    public static function parameters(MessagePurpose $purpose, Channel $channel): array
    {
        return self::PARAMETERS[$purpose->value][$channel->value]
            ?? throw new InvalidNotificationRequestException(\sprintf('%s has no %s template.', $purpose->value, $channel->value));
    }

    /**
     * Checks the parameters against what the template declares.
     *
     * @param array<array-key, mixed> $parameters
     *
     * @throws InvalidNotificationRequestException
     *
     * @return array<string, scalar|list<scalar>>
     */
    public static function validate(MessagePurpose $purpose, Channel $channel, array $parameters): array
    {
        $declared = self::parameters($purpose, $channel);
        $missing  = array_diff(array_keys($declared), array_keys($parameters));
        $extra    = array_diff(array_keys($parameters), array_keys($declared));

        if ([] !== $missing || [] !== $extra) {
            throw new InvalidNotificationRequestException(\sprintf(
                '%s %s parameters: missing [%s], unexpected [%s].',
                $purpose->value,
                $channel->value,
                implode(', ', $missing),
                implode(', ', $extra),
            ));
        }

        $valid = [];
        foreach ($declared as $name => $kind) {
            $value = $parameters[$name];

            if (self::LIST === $kind && \is_array($value) && array_is_list($value)) {
                $valid[$name] = array_map(static fn (mixed $item): bool|float|int|string => \is_scalar($item) ? $item : throw self::wrongType($purpose, $channel, $name, $kind), $value);
            } elseif (self::SCALAR === $kind && \is_scalar($value)) {
                $valid[$name] = $value;
            } else {
                throw self::wrongType($purpose, $channel, $name, $kind);
            }
        }

        return $valid;
    }

    private static function wrongType(MessagePurpose $purpose, Channel $channel, string $name, string $kind): InvalidNotificationRequestException
    {
        return new InvalidNotificationRequestException(\sprintf('%s %s parameter "%s" must be a %s.', $purpose->value, $channel->value, $name, self::LIST === $kind ? 'list of scalars' : 'scalar'));
    }
}
