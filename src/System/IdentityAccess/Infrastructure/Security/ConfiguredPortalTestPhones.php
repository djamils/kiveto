<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Security;

use App\Shared\Domain\ValueObject\PhoneNumber;
use App\System\IdentityAccess\Application\Port\PortalTestPhonesInterface;
use App\System\IdentityAccess\Domain\Otp\OtpPurpose;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * PORTAL_TEST_PHONES: comma-separated E.164 numbers, empty (the default)
 * for none. PORTAL_TEST_OTP_CODE: their code, six digits, required as soon
 * as one number is listed. Each request of a fixed code is logged on the
 * `portal_test_phone` channel, written in every environment.
 */
final readonly class ConfiguredPortalTestPhones implements PortalTestPhonesInterface
{
    /** @var list<string> E.164 */
    private array $phones;

    public function __construct(
        #[Autowire(env: 'PORTAL_TEST_PHONES')]
        string $phones,
        #[Autowire(env: 'PORTAL_TEST_OTP_CODE')]
        #[\SensitiveParameter]
        private string $code,
        #[Autowire(service: 'monolog.logger.portal_test_phone')]
        private LoggerInterface $logger,
    ) {
        $listed = array_filter(array_map(trim(...), explode(',', $phones)), static fn (string $phone): bool => '' !== $phone);

        $this->phones = array_values(array_map(static fn (string $phone): string => PhoneNumber::fromString($phone)->toString(), $listed));

        if ([] !== $this->phones && 1 !== preg_match('/^\d{6}$/D', $code)) {
            throw new \LogicException('PORTAL_TEST_OTP_CODE must be six digits when PORTAL_TEST_PHONES lists a number.');
        }
    }

    public function fixedCodeFor(PhoneNumber $phone, OtpPurpose $purpose): ?string
    {
        if (!\in_array($phone->toString(), $this->phones, true)) {
            return null;
        }

        $this->logger->notice('Fixed code requested for a Portal test phone; nothing is sent.', [
            'phone'   => $phone->toString(),
            'purpose' => $purpose->value,
        ]);

        return $this->code;
    }
}
