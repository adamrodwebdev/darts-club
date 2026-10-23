<?php

namespace App\MessageHandler;

use App\Message\SendMemberApprovedEmail;
use App\Repository\UserRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Translation\TranslatorInterface;

/** « Ton compte est validé » : l'espace membres est ouvert, dans la langue du membre. */
#[AsMessageHandler]
final class SendMemberApprovedEmailHandler
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly MailerInterface $mailer,
        private readonly TranslatorInterface $translator,
        #[Autowire('%env(FRONTEND_URL)%')]
        private readonly string $frontendUrl,
    ) {
    }

    public function __invoke(SendMemberApprovedEmail $message): void
    {
        $user = $this->users->find($message->userId);
        if (null === $user) {
            return;
        }

        $locale = $user->language()->value;
        $this->mailer->send((new TemplatedEmail())
            ->to($user->getEmail())
            ->locale($locale)
            ->subject($this->translator->trans('member_approved.subject', [], 'emails', $locale))
            ->htmlTemplate('emails/member_approved.html.twig')
            ->textTemplate('emails/member_approved.txt.twig')
            ->context(['loginUrl' => rtrim($this->frontendUrl, '/').'/connexion']));
    }
}
