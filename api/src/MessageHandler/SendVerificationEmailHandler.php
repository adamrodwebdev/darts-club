<?php

namespace App\MessageHandler;

use App\Message\SendVerificationEmail;
use App\Repository\UserRepository;
use App\Security\ThrottledMailer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

#[AsMessageHandler]
final class SendVerificationEmailHandler
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly VerifyEmailHelperInterface $verifyEmailHelper,
        private readonly ThrottledMailer $mailer,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(SendVerificationEmail $message): void
    {
        $user = $this->users->find($message->userId);
        if (null === $user || $user->isVerified()) {
            return;
        }

        $id = $user->getId()->toRfc4122();
        $signature = $this->verifyEmailHelper->generateSignature('auth_verify', $id, $user->getEmail(), ['id' => $id]);

        $this->mailer->send((new TemplatedEmail())
            ->to($user->getEmail())
            ->locale($user->language()->value)
            ->subject($this->translator->trans('verify.subject', [], 'emails', $user->language()->value))
            ->htmlTemplate('emails/verify_email.html.twig')
            ->textTemplate('emails/verify_email.txt.twig')
            ->context([
                'signedUrl' => $signature->getSignedUrl(),
                'expiresAt' => $signature->getExpiresAt(),
            ]));
    }
}
