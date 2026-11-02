<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\PortalRequest;

use App\Presentation\Clinic\Controller\Shared\AbstractSheetAction;
use App\Presentation\Clinic\ViewModel\Client\StaffNameResolver;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * What every answer of the « Demandes » sheet shares. The page module posts
 * it in the background and reads JSON (`{success, message}` and the
 * answer's own keys); a plain form post is sent back with a flash (PRG).
 */
abstract class AbstractPortalRequestAnswer extends AbstractSheetAction
{
    /**
     * Who answers: the staff member's user id and name, kept on the request.
     *
     * @return array{string, string}
     */
    protected function actor(StaffNameResolver $names, string $clinicId): array
    {
        $user = $this->getUser();
        \assert($user instanceof SecurityUser);

        return [$user->id(), $names->resolve($clinicId, $user)];
    }

    /**
     * @param string               $message the French outcome, toast or flash
     * @param string               $route   where a form post goes back to
     * @param array<string, mixed> $params  its route parameters
     * @param int                  $failure the JSON status of a refusal
     * @param array<string, mixed> $extra   more keys for the page module
     */
    protected function reply(Request $request, bool $done, string $message, string $route, array $params = [], int $failure = Response::HTTP_CONFLICT, array $extra = []): Response
    {
        if ($request->isXmlHttpRequest() || 'json' === $request->getPreferredFormat()) {
            return new JsonResponse(['success' => $done, 'message' => $message, ...$extra], $done ? Response::HTTP_OK : $failure);
        }

        $this->addFlash($done ? 'success' : 'error', $message);

        return $this->redirectToRoute($route, $params, Response::HTTP_SEE_OTHER);
    }
}
