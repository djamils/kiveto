<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\PrepareSecondFactor;

use App\System\IdentityAccess\Application\Port\SecondFactorSecretsInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PrepareSecondFactorHandler
{
    public function __construct(
        private SecondFactorSecretsInterface $secrets,
    ) {
    }

    public function __invoke(PrepareSecondFactor $query): SecondFactorEnrollment
    {
        return new SecondFactorEnrollment($this->secrets->newTotpSecret()->toBase32(), $query->email);
    }
}
