<?php

namespace App\ClubEvent;

use App\Entity\ClubEvent;
use App\Entity\GuestResponse;
use App\Preference\Language;
use App\Repository\GuestResponseRepository;
use App\ValueObject\DisplayName;
use App\ValueObject\EmailAddress;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

/** Cas d'usage des réponses sans compte (page publique d'un événement). */
final class GuestResponses
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
        private readonly GuestResponseRepository $responses,
        private readonly GuestMailer $mailer,
    ) {
    }

    /**
     * Enregistre la demande et envoie le lien de confirmation. Même effet visible
     * que l'adresse ait déjà répondu ou non : rien ne révèle qui participe.
     */
    public function request(ClubEvent $event, string $name, string $email, Attendance $answer, Language $language): void
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $response = $event->requestGuestResponse(
            DisplayName::fromString($name),
            EmailAddress::fromString($email),
            $answer,
            GuestResponse::hashToken($token),
            $language,
            $this->clock->now(),
        );
        $this->em->persist($response);
        $this->em->flush();

        $this->mailer->sendLink($response, $token);
    }

    public function find(string $token): ?GuestResponse
    {
        return $this->responses->findByToken($token);
    }

    public function confirm(GuestResponse $response): void
    {
        $response->getEvent()->confirmGuestResponse($response, $this->clock->now());
        $this->em->flush();
    }

    public function change(GuestResponse $response, Attendance $answer): void
    {
        $response->getEvent()->changeGuestAnswer($response, $answer, $this->clock->now());
        $this->em->flush();
    }

    public function cancel(GuestResponse $response): void
    {
        $response->getEvent()->removeGuestResponse($response, $this->clock->now());
        $this->em->flush();
    }

    public function purge(): int
    {
        return $this->responses->purge($this->clock->now());
    }
}
