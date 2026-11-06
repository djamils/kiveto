<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Account\CompleteClinicOnboardings;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Every active clinic still onboarding whose counted steps are all done
 * becomes an active pilot (its `onboardedAt` stamped once). Run every 15
 * minutes by the scheduler; answers how many changed.
 */
final readonly class CompleteClinicOnboardings implements CommandInterface
{
}
