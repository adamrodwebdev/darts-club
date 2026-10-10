<?php

namespace App\Translation;

use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * Règle métier violée par une valeur (objets valeur, entités) : message en français,
 * traduit dans la langue de la requête au moment de la réponse HTTP.
 */
final class InvalidValue extends \InvalidArgumentException implements TranslatableInterface
{
    use TranslatableError;

    /** @param array<string, string|int> $parameters ex. ['%max%' => 120] */
    public function __construct(string $message, array $parameters = [], ?\Throwable $previous = null)
    {
        parent::__construct($this->remember($message, $parameters), 0, $previous);
    }
}
