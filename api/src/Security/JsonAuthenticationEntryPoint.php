<?php

namespace App\Security;

use App\Translation\ErrorMessages;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

final class JsonAuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly ErrorMessages $messages,
    ) {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse(['error' => $this->messages->trans('Authentification requise.', $request)], Response::HTTP_UNAUTHORIZED);
    }
}
