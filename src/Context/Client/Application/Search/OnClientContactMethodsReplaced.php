<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Search;

use App\Context\Client\Domain\Event\ClientContactMethodsReplaced;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'messenger.bus.event')]
final readonly class OnClientContactMethodsReplaced
{
    public function __construct(
        private ClientSearchIndexWriterInterface $writer,
    ) {
    }

    public function __invoke(ClientContactMethodsReplaced $event): void
    {
        $payload = $event->payload();
        \assert(\is_string($payload['clientId']));
        \assert(\is_string($payload['clinicId']));
        \assert(\is_array($payload['contactMethods']));

        /** @var array<int, array{type: string, label: string, value: string, isPrimary: bool}> $contactMethods */
        $contactMethods  = $payload['contactMethods'];
        [$phone, $email] = $this->extractContactMethods($contactMethods);

        $this->writer->updateContactMethods(
            $payload['clientId'],
            $payload['clinicId'],
            $phone,
            $email,
        );
    }

    /**
     * @param array<int, array{type: string, label: string, value: string, isPrimary: bool}> $contactMethods
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function extractContactMethods(array $contactMethods): array
    {
        $phone = null;
        $email = null;

        foreach ($contactMethods as $method) {
            if ('phone' === $method['type'] && true === $method['isPrimary']) {
                $phone = $method['value'];
            }

            if ('email' === $method['type'] && true === $method['isPrimary']) {
                $email = $method['value'];
            }
        }

        return [$phone, $email];
    }
}
