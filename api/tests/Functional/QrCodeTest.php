<?php

namespace App\Tests\Functional;

use App\ClubEvent\EventDetails;
use App\Entity\ClubEvent;
use App\Enum\Role;
use App\Media\QrCodeSvg;
use App\Tests\ApiTestCase;

/** QR codes des affiches : le site, ou la page publique d'une soirée ; organisateurs seulement. */
final class QrCodeTest extends ApiTestCase
{
    public function testOrganizersPrintTheSiteOrAPublicEvening(): void
    {
        $public = $this->evening(public: true);
        $membersOnly = $this->evening(public: false);
        $secretary = $this->createUser('secretary@example.com');
        $secretary->grantRole(Role::Secretary);
        $this->entityManager()->flush();
        $this->loginWithTwoFactor('secretary@example.com', $this->enableTwoFactor($secretary));
        $qrCodes = new QrCodeSvg();

        $this->client->request('GET', '/api/qr/site.svg');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/svg+xml');
        self::assertSame($qrCodes->of(self::FRONT_ORIGIN.'/'), $this->client->getResponse()->getContent());

        $this->client->request('GET', "/api/events/{$public->getId()->toRfc4122()}/qr.svg");
        self::assertResponseIsSuccessful();
        self::assertSame($qrCodes->of(self::FRONT_ORIGIN.'/e/'.$public->getSlug()), $this->client->getResponse()->getContent());
        self::assertResponseHeaderSame('Content-Disposition', 'inline; filename="qr-'.$public->getSlug().'.svg"');

        // Une soirée réservée aux membres n'a pas de page publique
        $this->client->request('GET', "/api/events/{$membersOnly->getId()->toRfc4122()}/qr.svg");
        self::assertResponseStatusCodeSame(404);
    }

    public function testMembersDoNotPrintPosters(): void
    {
        $this->createUser('alice@example.com');
        $this->login('alice@example.com', self::PASSWORD);

        $this->client->request('GET', '/api/qr/site.svg');
        self::assertResponseStatusCodeSame(403);
    }

    private function evening(bool $public): ClubEvent
    {
        $organizer = $this->createUser($public ? 'organizer@example.com' : 'organizer2@example.com');
        $startsAt = new \DateTimeImmutable('2026-11-14 20:00 Europe/Paris');
        $event = ClubEvent::schedule(
            $organizer,
            new EventDetails('Soirée fléchettes', 'Initiation', 'Salle des fêtes, Bellevesvre', $startsAt, $startsAt->modify('+4 hours'), null, $public, $public ? 'Bellevesvre' : null),
            new \DateTimeImmutable('2026-10-15 12:00 UTC'),
        );
        $event->releaseEvents();
        $event->assignPublicAddress('a1b2c3');
        $this->entityManager()->persist($event);
        $this->entityManager()->flush();

        return $event;
    }
}
