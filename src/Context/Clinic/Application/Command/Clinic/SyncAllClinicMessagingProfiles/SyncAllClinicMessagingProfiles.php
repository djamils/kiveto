<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Clinic\SyncAllClinicMessagingProfiles;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Pushes the messaging profile and today's veterinarian count of every
 * clinic to Notification: after a deployment, and for clinics created
 * without domain events (fixtures).
 */
final readonly class SyncAllClinicMessagingProfiles implements CommandInterface
{
}
