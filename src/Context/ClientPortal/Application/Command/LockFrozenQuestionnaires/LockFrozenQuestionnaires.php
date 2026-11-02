<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\LockFrozenQuestionnaires;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Every 5 minutes (schedule `client_portal`): records the lock of the open
 * questionnaires whose appointment started or had a consultation begun
 * from it, so a reschedule made afterwards never reopens them (AC 232).
 */
final readonly class LockFrozenQuestionnaires implements CommandInterface
{
}
