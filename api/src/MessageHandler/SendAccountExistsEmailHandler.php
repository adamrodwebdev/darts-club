<?php

namespace App\MessageHandler;

use App\Message\SendAccountExistsEmail;
use App\Repository\UserRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SendAccountExistsEmailHandler
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly MailerInterface $mailer,
        #[Autowire('%env(FRONTEND_URL)%')]
        private readonly string $frontendUrl,
    ) {
    }

    public function __invoke(SendAccountExistsEmail $message): void
    {
        $user = $this->users->find($message->userId);
        if (null === $user) {
            return;
        }

        $this->mailer->send((new TemplatedEmail())
            ->to($user->getEmail())
            ->subject('Tentative d\'inscription avec ton adresse')
            ->htmlTemplate('emails/account_exists.html.twig')
            ->textTemplate('emails/account_exists.txt.twig')
            ->context(['loginUrl' => rtrim($this->frontendUrl, '/').'/connexion']));
    }
}
