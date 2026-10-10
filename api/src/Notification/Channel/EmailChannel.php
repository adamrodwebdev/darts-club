<?php

namespace App\Notification\Channel;

use App\Entity\User;
use App\Notification\Channel;
use App\Notification\NotificationChannelInterface;
use App\Notification\NotificationContent;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;

final class EmailChannel implements NotificationChannelInterface
{
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire('%env(FRONTEND_URL)%')]
        private readonly string $frontendUrl,
    ) {
    }

    public function channel(): Channel
    {
        return Channel::Email;
    }

    public function send(User $recipient, NotificationContent $content): void
    {
        $base = rtrim($this->frontendUrl, '/');

        $this->mailer->send((new TemplatedEmail())
            ->to($recipient->getEmail())
            ->subject($content->labelledTitle())
            ->htmlTemplate('emails/notification.html.twig')
            ->textTemplate('emails/notification.txt.twig')
            ->context([
                'content' => $content,
                'url' => $base.($content->link ?? '/'),
                'preferencesUrl' => $base.'/compte/notifications',
            ]));
    }
}
