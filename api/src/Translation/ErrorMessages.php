<?php

namespace App\Translation;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Messages d'erreur des réponses construites hors contrôleur (pare-feu, écouteurs) :
 * certains s'exécutent avant que la langue de la requête soit posée, d'où la
 * négociation directe sur Accept-Language.
 */
final class ErrorMessages
{
    /** @param list<string> $locales */
    public function __construct(
        private readonly TranslatorInterface $translator,
        #[Autowire('%kernel.enabled_locales%')]
        private readonly array $locales,
    ) {
    }

    /** Message du domaine « errors » dans la langue préférée de la requête */
    public function trans(string $message, Request $request): string
    {
        return $this->translator->trans($message, [], 'errors', $request->getPreferredLanguage($this->locales));
    }
}
