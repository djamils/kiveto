<?php

declare(strict_types=1);

namespace App\Presentation\Api\Shared\Idempotency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Presentation\Api\Shared\Problem\ProblemFactoryInterface;
use App\Shared\Domain\Idempotency\IdempotencyKey;
use App\Shared\Domain\Idempotency\IdempotencyKeyReusedException;
use App\Shared\Domain\Idempotency\IdempotencyStoreInterface;
use App\Shared\Domain\Idempotency\InvalidIdempotencyKeyException;
use App\Shared\Domain\Idempotency\RequestFingerprint;
use App\Shared\Domain\Idempotency\StoredResponse;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

/**
 * Outermost API Platform processor (wraps write, serialize and respond) for
 * operations declaring `extraProperties: ['idempotent' => true]`.
 *
 * - `Idempotency-Key` header required → otherwise `400 idempotency_key_missing`
 *   (also for a malformed key: 1 to 128 visible ASCII characters);
 * - first request → the operation runs, its response (status + body) is stored
 *   for 24 h;
 * - retry with the same key and the same request (method, URI, raw body) → the
 *   stored response is replayed without running the operation again, with the
 *   `Idempotent-Replayed: true` header;
 * - same key, different request → `422 idempotency_key_reused`.
 *
 * Both problems are built by the API's ProblemFactoryInterface (its codes
 * and titles).
 *
 * Only responses are stored: an operation that throws (validation, domain
 * error) records nothing, so the client can retry with the same key.
 * Replayed bodies are JSON (the API's only format) and come back from a MySQL
 * JSON column, so they are semantically equal to the original, not byte-equal.
 *
 * Principal: the authenticated user's identifier, which is the user id (UUID)
 * on the Portal API firewall (JWT `sub`). Unauthenticated callers share the nil
 * UUID as principal: their keys live in one namespace, so they must be random
 * (UUIDs), as any idempotency key should be.
 *
 * Symfony applies lower-priority decorators last (outermost): -100 puts this
 * processor above API Platform's validate/write/serialize/respond chain, so a
 * missing key or a replay never reaches the operation.
 *
 * @implements ProcessorInterface<mixed, mixed>
 */
#[AsDecorator(decorates: 'api_platform.state_processor.main', priority: -100)]
final readonly class IdempotentProcessor implements ProcessorInterface
{
    public const string HEADER = 'Idempotency-Key';

    public const string REPLAYED_HEADER = 'Idempotent-Replayed';

    public const string ANONYMOUS_PRINCIPAL = '00000000-0000-0000-0000-000000000000';

    /**
     * @param ProcessorInterface<mixed, mixed> $inner
     */
    public function __construct(
        #[AutowireDecorated]
        private ProcessorInterface $inner,
        private IdempotencyStoreInterface $store,
        private Security $security,
        private ProblemFactoryInterface $problems,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $request = $context['request'] ?? null;

        if (true !== ($operation->getExtraProperties()['idempotent'] ?? false) || !$request instanceof Request) {
            return $this->inner->process($data, $operation, $uriVariables, $context);
        }

        $key = $this->key($request);

        $executed = null;

        try {
            $stored = $this->store->execute(
                $this->principal(),
                $key,
                RequestFingerprint::of(implode("\n", [$request->getMethod(), $request->getRequestUri(), $request->getContent()])),
                function () use ($data, $operation, $uriVariables, $context, &$executed): StoredResponse {
                    $response = $this->inner->process($data, $operation, $uriVariables, $context);

                    if (!$response instanceof Response) {
                        throw new \LogicException('An idempotent operation must produce an HTTP response.');
                    }

                    $executed = $response;

                    return StoredResponse::create($response->getStatusCode(), (string) $response->getContent());
                },
            );
        } catch (IdempotencyKeyReusedException $exception) {
            throw $this->problems->create(
                ProblemFactoryInterface::IDEMPOTENCY_KEY_REUSED,
                'Cette clé a déjà servi pour une requête différente.',
                previous: $exception,
            );
        }

        if ($executed instanceof Response) {
            return $executed;
        }

        $body = $stored->body();

        return new Response(
            $body ?? '',
            $stored->status(),
            null === $body
                ? [self::REPLAYED_HEADER => 'true']
                : [self::REPLAYED_HEADER => 'true', 'Content-Type' => 'application/json'],
        );
    }

    private function key(Request $request): IdempotencyKey
    {
        $header = $request->headers->get(self::HEADER);

        try {
            return IdempotencyKey::fromString($header ?? '');
        } catch (InvalidIdempotencyKeyException $exception) {
            throw $this->problems->create(
                ProblemFactoryInterface::IDEMPOTENCY_KEY_MISSING,
                \sprintf('L\'en-tête %s est obligatoire : de 1 à %d caractères ASCII visibles.', self::HEADER, IdempotencyKey::MAX_LENGTH),
                previous: $exception,
            );
        }
    }

    private function principal(): string
    {
        $identifier = $this->security->getUser()?->getUserIdentifier();

        if (null === $identifier) {
            return self::ANONYMOUS_PRINCIPAL;
        }

        if (!Uuid::isValid($identifier)) {
            throw new \LogicException('Idempotent operations require a user identified by a UUID.');
        }

        return $identifier;
    }
}
