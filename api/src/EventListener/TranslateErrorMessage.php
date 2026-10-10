<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Traduit le message des erreurs 4xx dans la langue de la requête avant leur rendu.
 * Priorité au message traduisible le plus profond (celui qui porte les paramètres),
 * sinon le texte français est cherché comme clé du domaine « errors ».
 */
final class TranslateErrorMessage
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[AsEventListener(event: KernelEvents::EXCEPTION, priority: 64)]
    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if (!$exception instanceof HttpExceptionInterface || $exception->getStatusCode() >= 500 || '' === $exception->getMessage()) {
            return;
        }

        $locale = $event->getRequest()->getLocale();
        $translated = $this->translate($exception, $locale);
        if ($translated === $exception->getMessage()) {
            return;
        }

        $event->setThrowable(new HttpException($exception->getStatusCode(), $translated, $exception, $exception->getHeaders()));
    }

    private function translate(\Throwable $exception, string $locale): string
    {
        $innermost = null;
        for ($e = $exception; null !== $e; $e = $e->getPrevious()) {
            if ($e instanceof TranslatableInterface) {
                $innermost = $e;
            }
        }

        return null !== $innermost
            ? $innermost->trans($this->translator, $locale)
            : $this->translator->trans($exception->getMessage(), [], 'errors', $locale);
    }
}
