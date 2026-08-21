<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Consultation\Record;

use App\Presentation\Clinic\ViewModel\Consultation\CockpitStateBuilder;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\CommandInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Shared plumbing behind every cockpit mutation endpoint: the page-level CSRF
 * check, the current clinic and practitioner, the `{success, errors, errorCode}`
 * envelope, and the refreshed consultation state returned on success so the
 * client re-renders from server truth.
 */
final readonly class CockpitEndpoint
{
    public const string CSRF_INTENT = 'consultation_edit';

    public function __construct(
        private CommandBusInterface $commandBus,
        private CurrentClinicContextInterface $currentClinicContext,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private CockpitStateBuilder $stateBuilder,
        private Security $security,
    ) {
    }

    /**
     * Runs one cockpit mutation end to end.
     *
     * @param callable(string $clinicId, string $userId): CommandInterface $buildCommand
     */
    public function run(Request $request, string $consultationId, callable $buildCommand): JsonResponse
    {
        if (!$this->isCsrfValid($request)) {
            return self::error(
                Response::HTTP_FORBIDDEN,
                'CSRF_INVALID',
                ['global' => ['Session expirée, rechargez la page.']],
            );
        }

        $clinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $clinicId);

        $user = $this->security->getUser();
        \assert($user instanceof SecurityUser);

        try {
            $this->commandBus->dispatch($buildCommand($clinicId->toString(), $user->id()));
        } catch (HandlerFailedException $exception) {
            return $this->mapException($exception->getPrevious() ?? $exception);
        } catch (\Throwable $exception) {
            return $this->mapException($exception);
        }

        return new JsonResponse([
            'success'      => true,
            'consultation' => $this->stateBuilder->buildFor($consultationId, $clinicId->toString()),
        ]);
    }

    public function isCsrfValid(Request $request): bool
    {
        return $this->csrfTokenManager->isTokenValid(
            new CsrfToken(self::CSRF_INTENT, $request->request->getString('_token')),
        );
    }

    /**
     * Reads an optional positive integer field.
     *
     * The cockpit posts every field of a form, so a blank day count arrives as
     * an empty string — which `InputBag::getInt()` rejects with a 400.
     */
    public static function optionalPositiveInt(Request $request, string $key): ?int
    {
        $raw = trim($request->request->getString($key));

        if ('' === $raw || 1 !== preg_match('/^\d+$/', $raw)) {
            return null;
        }

        $value = (int) $raw;

        return $value > 0 ? $value : null;
    }

    /**
     * Reads a repeated field as a list of strings.
     *
     * `InputBag::all()` throws a 400 outside the JSON envelope when the field is
     * posted as a scalar, so the raw bag is read instead.
     *
     * @return list<string>
     */
    public static function stringList(Request $request, string $key): array
    {
        $raw = $request->request->all()[$key] ?? [];

        if (!\is_array($raw)) {
            return [];
        }

        $values = [];

        foreach ($raw as $value) {
            if (\is_string($value) && '' !== trim($value)) {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * Reads a repeated field as a map of strings, dropping empty entries.
     *
     * @return array<string, string>
     */
    public static function stringMap(Request $request, string $key): array
    {
        $raw = $request->request->all()[$key] ?? [];

        if (!\is_array($raw)) {
            return [];
        }

        $values = [];

        foreach ($raw as $field => $value) {
            if (\is_string($field) && \is_scalar($value) && '' !== (string) $value) {
                $values[$field] = (string) $value;
            }
        }

        return $values;
    }

    public static function quantity(Request $request, string $key = 'quantity'): float
    {
        $raw = trim($request->request->getString($key));

        return is_numeric($raw) ? (float) $raw : 1.0;
    }

    public function currentClinicId(): string
    {
        $clinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $clinicId);

        return $clinicId->toString();
    }

    private function mapException(\Throwable $exception): JsonResponse
    {
        return match (true) {
            $exception instanceof OptimisticLockException => self::error(
                Response::HTTP_CONFLICT,
                'CONFLICT',
                ['global' => ['Modification concurrente détectée.']],
            ),
            $exception instanceof \DomainException => self::error(
                Response::HTTP_CONFLICT,
                'DOMAIN_ERROR',
                ['global' => [$exception->getMessage()]],
            ),
            $exception instanceof \InvalidArgumentException => self::error(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'VALIDATION',
                ['global' => [$exception->getMessage()]],
            ),
            default => throw $exception,
        };
    }

    /**
     * @param array<string, list<string>> $errors
     */
    private static function error(int $status, string $errorCode, array $errors): JsonResponse
    {
        return new JsonResponse(
            ['success' => false, 'errorCode' => $errorCode, 'errors' => $errors],
            $status,
        );
    }
}
