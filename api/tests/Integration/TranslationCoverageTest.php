<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Translation\TranslatorBagInterface;

/**
 * Contrôle des traductions manquantes : chaque message français présenté à
 * l'utilisateur (exceptions, contraintes, réponses du pare-feu) a sa version
 * anglaise, et les catalogues à clés ont les mêmes clés en français et en anglais.
 */
final class TranslationCoverageTest extends KernelTestCase
{
    /** Classes d'exceptions internes : jamais montrées telles quelles à l'adhérent */
    private const INTERNAL = ['LogicException', 'RuntimeException', 'UnsupportedUserException'];

    /** Domaines à clés (« verify.subject »…) : les deux langues sont rédigées */
    private const KEYED_DOMAINS = ['emails', 'notifications', 'enums', 'exports'];

    public function testEveryFrenchSourceMessageIsTranslatedToEnglish(): void
    {
        $missing = [];
        foreach (self::sourceMessages() as [$domain, $message, $file]) {
            if (!$this->catalogue('en')->defines($message, $domain)) {
                $missing[] = \sprintf('[%s] %s (%s)', $domain, $message, $file);
            }
        }

        self::assertSame([], $missing, "Messages sans traduction anglaise :\n".implode("\n", $missing));
    }

    public function testKeyedCataloguesHaveTheSameKeysInBothLanguages(): void
    {
        foreach (self::KEYED_DOMAINS as $domain) {
            $fr = array_keys($this->catalogue('fr')->all($domain));
            $en = array_keys($this->catalogue('en')->all($domain));
            sort($fr);
            sort($en);

            self::assertNotSame([], $fr, \sprintf('Domaine « %s » vide.', $domain));
            self::assertSame($fr, $en, \sprintf('Clés différentes entre fr et en dans « %s ».', $domain));
        }
    }

    public function testEveryTranslationKeyUsedInTheCodeExists(): void
    {
        $missing = [];
        foreach (self::usedKeys() as [$domain, $key, $file]) {
            foreach (['fr', 'en'] as $locale) {
                if (!$this->catalogue($locale)->defines($key, $domain)) {
                    $missing[] = \sprintf('[%s/%s] %s (%s)', $locale, $domain, $key, $file);
                }
            }
        }

        self::assertSame([], $missing, "Clés absentes :\n".implode("\n", $missing));
    }

    private function catalogue(string $locale): \Symfony\Component\Translation\MessageCatalogueInterface
    {
        $translator = self::getContainer()->get('translator');
        \assert($translator instanceof TranslatorBagInterface);

        return $translator->getCatalogue($locale);
    }

    /** @return iterable<array{string, string, string}> [domaine, message français, fichier] */
    private static function sourceMessages(): iterable
    {
        foreach (self::sources() as $file => $code) {
            preg_match_all("/throw new \\\\?(\\w+)\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/", $code, $throws, \PREG_SET_ORDER);
            foreach ($throws as [, $class, $message]) {
                if (\in_array($class, self::INTERNAL, true)) {
                    continue;
                }
                yield [str_starts_with($class, 'CustomUserMessage') ? 'security' : 'errors', stripslashes($message), $file];
            }

            // Réponses JSON du pare-feu et des écouteurs (ErrorMessages)
            preg_match_all("/messages->trans\\(\\s*'((?:[^'\\\\]|\\\\.)*)'(?:[^)]*domain: '(\\w+)')?/", $code, $calls, \PREG_SET_ORDER);
            foreach ($calls as $call) {
                yield [$call[2] ?? 'errors', stripslashes($call[1]), $file];
            }

            // Exceptions de connexion à message fixe
            preg_match_all("/extends CustomUserMessage\\w+.*?parent::__construct\\('((?:[^'\\\\]|\\\\.)*)'/s", $code, $statuses, \PREG_SET_ORDER);
            foreach ($statuses as [, $message]) {
                yield ['security', stripslashes($message), $file];
            }

            // Messages des contraintes de validation
            preg_match_all("/message: '((?:[^'\\\\]|\\\\.)*)'/", $code, $constraints, \PREG_SET_ORDER);
            foreach ($constraints as [, $message]) {
                yield ['validators', stripslashes($message), $file];
            }
        }
    }

    /** @return iterable<array{string, string, string}> [domaine, clé, fichier] */
    private static function usedKeys(): iterable
    {
        foreach (self::sources() as $file => $code) {
            preg_match_all("/(?:TranslatableMessage|->trans)\\(\\s*'([a-z_]+\\.[a-z_.]+)'.*?'(emails|notifications|enums|exports)'/", $code, $calls, \PREG_SET_ORDER);
            foreach ($calls as [, $key, $domain]) {
                yield [$domain, $key, $file];
            }
            // Clés construites dynamiquement : 'event.' + suffixe (vérifiées par les tests fonctionnels)
        }

        $templates = (new Finder())->files()->in(\dirname(__DIR__, 2).'/templates/emails')->name('*.twig');
        foreach ($templates as $template) {
            preg_match_all("/'([a-z_]+(?:\\.[a-z_]+)*)'\\|trans/", $template->getContents(), $keys);
            foreach ($keys[1] as $key) {
                yield ['emails', $key, $template->getFilename()];
            }
        }
    }

    /** @return iterable<string, string> */
    private static function sources(): iterable
    {
        $finder = (new Finder())->files()->in(\dirname(__DIR__, 2).'/src')->name('*.php')->notPath('Dev');
        foreach ($finder as $file) {
            yield $file->getRelativePathname() => $file->getContents();
        }
    }
}
