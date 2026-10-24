<?php

namespace App\Translation;

use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Typographie française des textes rédigés pour les adhérents (emails, notifications) : espaces
 * insécables appliquées au texte traduit, les fichiers de traduction restent lisibles. Les messages
 * d'erreur gardent leur texte exact (clés) ; l'écran les met en forme à l'affichage.
 */
#[AsDecorator(decorates: 'translator')]
final class FrenchTypographyTranslator implements TranslatorInterface, TranslatorBagInterface, LocaleAwareInterface, WarmableInterface
{
    private const DOMAINS = ['emails', 'notifications'];

    public function __construct(
        #[AutowireDecorated]
        private readonly TranslatorInterface&TranslatorBagInterface&LocaleAwareInterface $inner,
    ) {
    }

    /** @param array<string, mixed> $parameters */
    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        $text = $this->inner->trans($id, $parameters, $domain, $locale);

        return \in_array($domain, self::DOMAINS, true) ? FrenchTypography::forLocale($text, $locale ?? $this->inner->getLocale()) : $text;
    }

    public function getCatalogue(?string $locale = null): MessageCatalogueInterface
    {
        return $this->inner->getCatalogue($locale);
    }

    /** @return MessageCatalogueInterface[] */
    public function getCatalogues(): array
    {
        return $this->inner->getCatalogues();
    }

    public function setLocale(string $locale): void
    {
        $this->inner->setLocale($locale);
    }

    public function getLocale(): string
    {
        return $this->inner->getLocale();
    }

    /** @return string[] */
    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        return $this->inner instanceof WarmableInterface ? $this->inner->warmUp($cacheDir, $buildDir) : [];
    }
}
