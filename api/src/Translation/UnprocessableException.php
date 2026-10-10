<?php

namespace App\Translation;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Contracts\Translation\TranslatableInterface;

/** Erreur 422 dont le message est traduit dans la langue de la requête. */
abstract class UnprocessableException extends UnprocessableEntityHttpException implements TranslatableInterface
{
    use TranslatableError;

    /** @param array<string, string|int> $parameters ex. ['%max%' => 10] */
    public function __construct(string $message = '', ?\Throwable $previous = null, array $parameters = [])
    {
        parent::__construct($this->remember($message, $parameters), $previous);
    }
}
