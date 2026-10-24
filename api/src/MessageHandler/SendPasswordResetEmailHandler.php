<?php

namespace App\MessageHandler;

use App\Message\SendPasswordResetEmail;
use App\Repository\UserRepository;
use App\Security\PasswordResetLinks;
use App\Security\ThrottledMailer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Lien « mot de passe oublié », dans la langue du compte ; envoi plafonné (demande d'un visiteur). */
#[AsMessageHandler]
final class SendPasswordResetEmailHandler
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordResetLinks $links,
        private readonly ThrottledMailer $mailer,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(SendPasswordResetEmail $message): void
    {
        $user = $this->users->find($message->userId);
        if (null === $user || $user->isSuspended()) {
            return;
        }

        $locale = $user->language()->value;
        $this->mailer->send((new TemplatedEmail())
            ->to($user->getEmail())
            ->locale($locale)
            ->subject($this->translator->trans('password_reset.subject', [], 'emails', $locale))
            ->htmlTemplate('emails/password_reset.html.twig')
            ->textTemplate('emails/password_reset.txt.twig')
            ->context(['link' => $this->links->create($user), 'by_administrator' => $message->byAdministrator]));
    }
}
