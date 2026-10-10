<?php

namespace App\Dev;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Email;

/**
 * Développement uniquement : chaque email est enregistré en HTML dans
 * var/mails/ (liens de vérification, notifications…), sans serveur SMTP.
 */
#[When(env: 'dev')]
#[AsEventListener(priority: -255)] // après le rendu des gabarits Twig
final class DevMailCatcher
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/var/mails')]
        private readonly string $directory,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(MessageEvent $event): void
    {
        $message = $event->getMessage();
        if (!$message instanceof Email || $event->isQueued()) {
            return;
        }

        $to = implode(', ', array_map(static fn ($a): string => $a->getAddress(), $message->getTo()));
        $subject = (string) $message->getSubject();
        $slug = trim((string) preg_replace('/[^a-z0-9]+/i', '-', $subject), '-');
        $file = \sprintf('%s/%s-%s.html', $this->directory, date('Ymd-His'), substr($slug, 0, 50));

        $body = $message->getHtmlBody();
        $html = \is_string($body) ? $body : nl2br(htmlspecialchars((string) $message->getTextBody()));

        (new Filesystem())->dumpFile($file, \sprintf(
            '<!doctype html><meta charset="utf-8"><p><b>À :</b> %s<br><b>Sujet :</b> %s</p><hr>%s',
            htmlspecialchars($to),
            htmlspecialchars($subject),
            $html,
        ));
        $this->logger->info('Email de dev enregistré : {file}', ['file' => $file]);
    }
}
