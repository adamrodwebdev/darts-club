<?php

namespace App\Translation;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Message d'erreur traduisible : le texte français sert de clé dans le domaine
 * « errors » (translations/errors.<langue>.yaml), avec des paramètres %nom%.
 */
trait TranslatableError
{
    public const DOMAIN = 'errors';

    /** @var array<string, string|int> */
    private array $parameters = [];

    private string $template = '';

    /** @param array<string, string|int> $parameters */
    private function remember(string $template, array $parameters): string
    {
        $this->template = $template;
        $this->parameters = $parameters;

        return strtr($template, array_map(strval(...), $parameters));
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans($this->template, $this->parameters, self::DOMAIN, $locale);
    }
}
