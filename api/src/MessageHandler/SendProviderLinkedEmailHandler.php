<?php

namespace App\MessageHandler;

use App\Message\SendProviderLinkedEmail;
use App\Repository\UserRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Translation\TranslatorInterface;

/** « Connexion Google ajoutée à ton compte » : si ce n'était pas le titulaire, il le voit tout de suite. */
#[AsMessageHandler]
final class SendProviderLinkedEmailHandler
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly MailerInterface $mailer,
        private readonly TranslatorInterface $translator,
        #[Autowire('%env(FRONTEND_URL)%')]
        private readonly string $frontendUrl,
    ) {
    }

    public function __invoke(SendProviderLinkedEmail $message): void
    {
        $user = $this->users->find($message->userId);
        if (null === $user) {
            return;
        }

        $locale = $user->language()->value;
        $provider = ucfirst($message->provider);

        $this->mailer->send((new TemplatedEmail())
            ->to($user->getEmail())
            ->locale($locale)
            ->subject($this->translator->trans('provider_linked.subject', ['%provider%' => $provider], 'emails', $locale))
            ->htmlTemplate('emails/provider_linked.html.twig')
            ->textTemplate('emails/provider_linked.txt.twig')
            ->context(['provider' => $provider, 'securityUrl' => rtrim($this->frontendUrl, '/').'/compte/securite']));
    }
}
