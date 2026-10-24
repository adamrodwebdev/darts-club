<?php

namespace App\MessageHandler;

use App\Message\SendPasskeyNotice;
use App\Repository\UserRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Alerte de sécurité sur les clés d'accès, dans la langue du compte. */
#[AsMessageHandler]
final class SendPasskeyNoticeHandler
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly MailerInterface $mailer,
        private readonly TranslatorInterface $translator,
        #[Autowire('%env(FRONTEND_URL)%')]
        private readonly string $frontendUrl,
    ) {
    }

    public function __invoke(SendPasskeyNotice $message): void
    {
        $user = $this->users->find($message->userId);
        if (null === $user) {
            return;
        }

        $locale = $user->language()->value;
        $this->mailer->send((new TemplatedEmail())
            ->to($user->getEmail())
            ->locale($locale)
            ->subject($this->translator->trans('passkey.'.$message->notice.'.subject', [], 'emails', $locale))
            ->htmlTemplate('emails/passkey_notice.html.twig')
            ->textTemplate('emails/passkey_notice.txt.twig')
            ->context(['notice' => $message->notice, 'securityUrl' => rtrim($this->frontendUrl, '/').'/compte/securite']));
    }
}
