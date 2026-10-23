<?php

namespace App\Security;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Emails qu'un visiteur non connecté peut faire partir vers n'importe quelle adresse (confirmation
 * d'inscription, compte déjà existant, lien d'une réponse sans compte). Au plus 4 par jour vers une
 * même adresse, pour que personne ne soit inondé, et 100 par jour en tout, pour que le quota d'envoi
 * reste aux adhérents. Au-delà, l'email n'est pas envoyé (journalisé) : la réponse HTTP ne change
 * pas, elle ne révèle donc rien.
 */
final class ThrottledMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly RateLimiterFactoryInterface $visitorEmailRecipientLimiter,
        private readonly RateLimiterFactoryInterface $visitorEmailDailyLimiter,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function send(Email $email): void
    {
        $recipient = mb_strtolower(($email->getTo()[0] ?? null)?->getAddress() ?? '');

        if (!$this->visitorEmailRecipientLimiter->create($recipient)->consume()->isAccepted()) {
            $this->logger->warning('Email non envoyé : déjà 4 aujourd\'hui pour cette adresse.');

            return;
        }
        if (!$this->visitorEmailDailyLimiter->create('all')->consume()->isAccepted()) {
            $this->logger->error('Email non envoyé : plafond quotidien des emails demandés sans connexion atteint.');

            return;
        }

        $this->mailer->send($email);
    }
}
