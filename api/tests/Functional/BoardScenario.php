<?php

namespace App\Tests\Functional;

use App\ClubEvent\EventDetails;
use App\Entity\ClubEvent;
use App\Enum\Role;

/**
 * Bureau de test (rôles et double authentification) et soirées à préparer : pour les écrans du bureau
 * (bilan, démarches).
 */
trait BoardScenario
{
    /** @var array<string, string> secrets TOTP des membres du bureau */
    private array $secrets = [];

    private int $organizers = 0;

    /** @param array<string, Role> $members */
    private function board(array $members): void
    {
        $users = [];
        foreach ($members as $email => $role) {
            $users[$email] = $this->createUser($email);
            $users[$email]->grantRole($role);
        }
        $this->entityManager()->flush();
        foreach ($users as $email => $user) {
            $this->secrets[$email] = $this->enableTwoFactor($user);
        }
    }

    private function loginAs(string $email): void
    {
        $this->client->getCookieJar()->clear();
        $this->loginWithTwoFactor($email, $this->secrets[$email]);
    }

    private function evening(string $startsAt): ClubEvent
    {
        $organizer = $this->createUser('organizer'.++$this->organizers.'@example.com');
        $start = new \DateTimeImmutable($startsAt.' Europe/Paris');
        $event = ClubEvent::schedule(
            $organizer,
            new EventDetails('Soirée fléchettes', 'Initiation', 'Salle des fêtes, Bellevesvre', $start, $start->modify('+4 hours'), null, false, null),
            $start->modify('-60 days'),
        );
        $event->releaseEvents();
        $this->entityManager()->persist($event);
        $this->entityManager()->flush();

        return $event;
    }

    /** @param array<string, mixed> $payload */
    private function request(string $method, string $uri, array $payload = []): void
    {
        $this->client->request($method, $uri, server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => self::FRONT_ORIGIN], content: json_encode($payload, \JSON_THROW_ON_ERROR));
    }
}
