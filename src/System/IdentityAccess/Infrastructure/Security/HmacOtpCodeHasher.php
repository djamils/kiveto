<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Security;

use App\System\IdentityAccess\Application\Port\OtpCodeHasherInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class HmacOtpCodeHasher implements OtpCodeHasherInterface
{
    public function __construct(
        #[Autowire('%kernel.secret%')]
        private string $secret,
    ) {
    }

    public function hash(string $code): string
    {
        return hash_hmac('sha256', $code, $this->secret);
    }
}
