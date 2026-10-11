<?php

namespace App\ClubEvent;

use App\Entity\ClubEvent;
use App\Entity\GuestResponse;
use App\Translation\LocalizedDate;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Emails aux personnes sans compte, dans la langue de leur réponse :
 * lien de confirmation, puis rappel, changement d'horaire ou annulation.
 */
final class GuestMailer
{
    /** Messages de suivi : clé → réservé aux réponses « je viens » / « pas sûr » confirmées */
    public const UPDATES = ['reminder', 'rescheduled', 'cancelled'];

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly TranslatorInterface $translator,
        #[Autowire('%env(FRONTEND_URL)%')]
        private readonly string $frontendUrl,
    ) {
    }

    /** Lien personnel (le jeton n'est jamais stocké en clair : il n'existe que dans cet email). */
    public function sendLink(GuestResponse $response, string $token): void
    {
        $event = $response->getEvent();
        $locale = $response->language()->value;

        $this->mailer->send((new TemplatedEmail())
            ->to($response->email())
            ->locale($locale)
            ->subject($this->translator->trans('guest.link.subject', ['%title%' => $event->getTitle()], 'emails', $locale))
            ->htmlTemplate('emails/guest_link.html.twig')
            ->textTemplate('emails/guest_link.txt.twig')
            ->context([
                'title' => $event->getTitle(),
                'answer' => $response->pendingAnswer() ?? $response->answer() ?? Attendance::Going,
                'date' => new LocalizedDate($event->getStartsAt()),
                'city' => (string) $event->getCity(),
                'link' => $this->pageUrl($event).'/reponse#'.$token,
            ]));
    }

    /** @param value-of<self::UPDATES> $kind */
    public function notifyGuests(ClubEvent $event, string $kind): int
    {
        $sent = 0;
        foreach ($event->confirmedGuests() as $guest) {
            $locale = $guest->language()->value;
            $this->mailer->send((new TemplatedEmail())
                ->to($guest->email())
                ->locale($locale)
                ->subject($this->translator->trans('guest.'.$kind.'.subject', ['%title%' => $event->getTitle()], 'emails', $locale))
                ->htmlTemplate('emails/guest_update.html.twig')
                ->textTemplate('emails/guest_update.txt.twig')
                ->context([
                    'introKey' => 'guest.'.$kind.'.intro',
                    'title' => $event->getTitle(),
                    'reason' => (string) $event->getCancelReason(),
                    'answer' => $guest->answer() ?? Attendance::Going,
                    'date' => new LocalizedDate($event->getStartsAt()),
                    // Réponse confirmée : l'adresse complète est communiquée
                    'location' => $event->getLocation(),
                    'link' => $this->pageUrl($event),
                ]));
            ++$sent;
        }

        return $sent;
    }

    private function pageUrl(ClubEvent $event): string
    {
        return rtrim($this->frontendUrl, '/').'/e/'.$event->getSlug();
    }
}
