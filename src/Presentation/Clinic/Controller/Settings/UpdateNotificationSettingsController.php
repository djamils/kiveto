<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Settings;

use App\Context\Clinic\Application\Command\Clinic\UpdateClinicMessagingSettings\UpdateClinicMessagingSettings;
use App\Context\Clinic\Domain\Exception\InvalidMessagingSettingsException;
use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Presentation\Clinic\ViewModel\Settings\SettingsEndpoint;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The « Notifications » section, saved whole on every change: the SMS
 * sender name and the SMS overage cap.
 */
#[Route(path: '/settings/notifications', name: 'clinic_settings_notifications_save', methods: ['POST'])]
final readonly class UpdateNotificationSettingsController
{
    private const string SENDER_REASON = 'Le nom d’expéditeur compte 11 caractères au plus : lettres sans accents, chiffres et espaces, dont au moins une lettre.';

    public function __construct(
        private CommandBusInterface $commandBus,
        private SettingsEndpoint $endpoint,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        if (null !== $refused = $this->endpoint->guard($request, ClinicPermissionGuard::MANAGE_STAFF)) {
            return $refused;
        }

        $v      = SettingsEndpoint::values($request);
        $sender = SettingsEndpoint::text($v, 'no.exp');
        $cap    = SettingsEndpoint::int($v, 'no.cap');

        if (null === $sender) {
            return $this->endpoint->refuse('Indiquez le nom d’expéditeur des SMS.', 'no.exp');
        }

        if (null === $cap) {
            return $this->endpoint->refuse('Indiquez le plafond de dépassement, en segments.', 'no.cap');
        }

        try {
            $this->commandBus->dispatch(new UpdateClinicMessagingSettings($this->endpoint->clinicId(), $sender, $cap));
        } catch (InvalidMessagingSettingsException $exception) {
            return InvalidMessagingSettingsException::FIELD_SENDER === $exception->field
                ? $this->endpoint->refuse(self::SENDER_REASON, 'no.exp')
                : $this->endpoint->refuse('Le plafond doit être compris entre 0 et 100 000 segments.', 'no.cap');
        }

        return $this->endpoint->ok();
    }
}
