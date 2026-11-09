<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\ClubEvent;
use App\Entity\EveningTeamMember;
use App\Enum\Role;
use App\Evening\Team;
use App\Evening\TeamAssignment;
use App\Tests\ApiTestCase;
use App\Tests\Double\MercureRecorder;

/**
 * Accueil d'une soirée (étape 13c) : vente de jetons et de sachets aux valeurs du bureau, ventes
 * annulées gardées, écrans des équipes ouverts le jour de la soirée seulement, jetons figés à la
 * première vente. Horloge des tests figée au 15 octobre 2026, 12 h (UTC).
 */
final class EveningEntranceTest extends ApiTestCase
{
    use BoardScenario;

    private const SALE = '01a1b2c3-0000-7000-8000-00000000a001';
    private const SECOND_SALE = '01a1b2c3-0000-7000-8000-00000000a002';

    public function testTheEntranceSellsTokensAtTheEveningsValues(): void
    {
        $event = $this->evening('2026-10-15 20:00');
        $this->board(['president@example.com' => Role::President]);
        $this->loginAs('president@example.com');

        // Valeurs prises dans les réglages de la soirée : une valeur envoyée par l'appareil est ignorée
        $this->postJson($this->salesUri($event), ['id' => self::SALE, 'method' => 'especes', 'lines' => [
            ['name' => 'Sachet', 'count' => 2, 'value' => '0.01'],
            ['name' => 'jeton vert', 'count' => 3],
        ]]);
        self::assertResponseStatusCodeSame(201);
        self::assertSame('23.00', $this->dig($this->json(), 'sales', 0, 'total'));
        self::assertSame(['Sachet', 'Jeton vert'], array_column((array) $this->dig($this->json(), 'sales', 0, 'lines'), 'name'));
        self::assertSame('vert', $this->dig($this->json(), 'sales', 0, 'lines', 1, 'color'));
        self::assertSame(['cash' => '23.00', 'card' => '0.00'], $this->json()['taken']);

        // Renvoyée après une coupure (même identifiant) : comptée une seule fois
        $this->postJson($this->salesUri($event), ['id' => self::SALE, 'method' => 'especes', 'lines' => [['name' => 'Sachet', 'count' => 2]]]);
        self::assertResponseStatusCodeSame(201);
        self::assertCount(1, (array) $this->json()['sales']);

        $this->postJson($this->salesUri($event), ['id' => self::SECOND_SALE, 'method' => 'carte', 'lines' => [['name' => 'Jeton jaune', 'count' => 5]]]);
        self::assertSame(['cash' => '23.00', 'card' => '2.50'], $this->json()['taken']);

        $this->postJson($this->salesUri($event), ['id' => '01a1b2c3-0000-7000-8000-00000000a003', 'method' => 'especes', 'lines' => [['name' => 'Jeton rouge', 'count' => 1]]]);
        self::assertResponseStatusCodeSame(422);
        $this->postJson($this->salesUri($event), ['id' => '01a1b2c3-0000-7000-8000-00000000a004', 'method' => 'cheque', 'lines' => [['name' => 'Sachet', 'count' => 1]]]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testACancelledSaleStaysCrossedOutAndGoesToTheAuditTrail(): void
    {
        $event = $this->evening('2026-10-15 20:00');
        $this->board(['treasurer@example.com' => Role::Treasurer]);
        $this->loginAs('treasurer@example.com');
        $this->postJson($this->salesUri($event), ['id' => self::SALE, 'method' => 'especes', 'lines' => [['name' => 'Sachet', 'count' => 1]]]);

        $this->postJson('/api/token-sales/'.self::SALE.'/cancel', []);
        self::assertResponseIsSuccessful();
        self::assertTrue($this->dig($this->json(), 'sales', 0, 'cancelled'));
        self::assertSame('treasurer', $this->dig($this->json(), 'sales', 0, 'cancelledBy'));
        self::assertSame(['cash' => '0.00', 'card' => '0.00'], $this->json()['taken']);

        // Deuxième appareil, même vente : « déjà annulée »
        $this->postJson('/api/token-sales/'.self::SALE.'/cancel', []);
        self::assertResponseStatusCodeSame(409);
        self::assertSame(['entrance.sale_cancel'], array_column($this->auditTrail(), 'action'));
    }

    public function testTeamScreensOpenOnTheEveningDayOnly(): void
    {
        $tonight = $this->evening('2026-10-15 20:00');
        $nextMonth = $this->evening('2026-11-21 20:00');
        $lastNight = $this->evening('2026-10-14 20:00');
        $this->board(['secretary@example.com' => Role::Secretary]);
        $nadia = $this->createUser('nadia@example.com');
        $marc = $this->createUser('marc@example.com');
        foreach ([$tonight, $nextMonth, $lastNight] as $event) {
            $this->assign($event, new TeamAssignment($nadia, Team::Welcome));
            $this->assign($event, new TeamAssignment($marc, Team::Bar));
        }

        $this->client->getCookieJar()->clear();
        $this->login('nadia@example.com', self::PASSWORD);
        $this->client->request('GET', $this->entranceUri($tonight));
        self::assertResponseIsSuccessful();
        self::assertSame(['Jeton vert', 'Jeton jaune', 'Sachet'], array_column((array) $this->json()['tokens'], 'name'));
        // La page de la soirée propose son écran, et seulement le sien
        $this->client->request('GET', '/api/events/'.$tonight->getId()->toRfc4122());
        self::assertSame(['entrance' => true, 'bar' => false, 'kitchen' => false], $this->json()['service']);
        $this->client->request('GET', $this->entranceUri($nextMonth));
        self::assertResponseStatusCodeSame(403, 'Pas avant le jour de la soirée');
        $this->client->request('GET', $this->entranceUri($lastNight));
        self::assertResponseStatusCodeSame(403, 'Plus six heures après la fin');

        // L'équipe du bar n'ouvre pas l'accueil
        $this->client->getCookieJar()->clear();
        $this->login('marc@example.com', self::PASSWORD);
        $this->client->request('GET', $this->entranceUri($tonight));
        self::assertResponseStatusCodeSame(403);

        // Le bureau ouvre tout, à tout moment
        $this->loginAs('secretary@example.com');
        $this->client->request('GET', $this->entranceUri($nextMonth));
        self::assertResponseIsSuccessful();
    }

    public function testTokensFreezeAtTheFirstSale(): void
    {
        $event = $this->evening('2026-10-15 20:00');
        $this->board(['secretary@example.com' => Role::Secretary]);
        $this->loginAs('secretary@example.com');
        $tokens = [['name' => 'Jeton vert', 'value' => '1', 'bag' => false, 'color' => 'vert'], ['name' => 'Jeton jaune', 'value' => '0.50', 'bag' => false, 'color' => 'jaune'], ['name' => 'Sachet', 'value' => '10', 'bag' => true]];
        $this->request('PUT', $this->accountsUri($event).'/cash', ['tokensEnabled' => true, 'tokens' => $tokens, 'cashFloat' => '50']);
        self::assertResponseIsSuccessful();
        $this->postJson($this->salesUri($event), ['id' => self::SALE, 'method' => 'especes', 'lines' => [['name' => 'Sachet', 'count' => 1]]]);

        $tokens[2]['value'] = '12';
        $this->request('PUT', $this->accountsUri($event).'/cash', ['tokensEnabled' => true, 'tokens' => $tokens, 'cashFloat' => '50']);
        self::assertResponseStatusCodeSame(422);

        // Le fond de caisse et les couleurs changent encore
        $tokens[2]['value'] = '10';
        $tokens[1]['color'] = 'orange';
        $this->request('PUT', $this->accountsUri($event).'/cash', ['tokensEnabled' => true, 'tokens' => $tokens, 'cashFloat' => '80']);
        self::assertResponseIsSuccessful();
        self::assertSame(['80.00', 'orange', true], [$this->dig($this->json(), 'cash', 'cashFloat'), $this->dig($this->json(), 'cash', 'tokens', 1, 'color'), $this->dig($this->json(), 'cash', 'frozen')]);
    }

    public function testSalesReachTheEveningScreensInRealTime(): void
    {
        $event = $this->evening('2026-10-15 20:00');
        $this->board(['president@example.com' => Role::President]);
        $nadia = $this->createUser('nadia@example.com');
        $this->createUser('alice@example.com');
        $this->assign($event, new TeamAssignment($nadia, Team::Welcome));
        $this->loginAs('president@example.com');

        $this->postJson($this->salesUri($event), ['id' => self::SALE, 'method' => 'carte', 'lines' => [['name' => 'Sachet', 'count' => 1]]]);
        $published = $this->service(MercureRecorder::class, MercureRecorder::class)->published;
        $topic = 'https://darts-club.app/evenings/'.$event->getId()->toRfc4122().'/service';
        self::assertSame([[$topic], true], [$published[0]['topics'] ?? null, $published[0]['private'] ?? null]);
        $update = json_decode($published[0]['data'] ?? '', true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($update);
        self::assertSame('sale', $update['kind'] ?? null);

        // Le cookie d'une équipière couvre le topic de sa soirée ; celui d'une adhérente sans équipe, non
        $this->client->getCookieJar()->clear();
        $this->login('nadia@example.com', self::PASSWORD);
        $this->client->request('POST', '/api/realtime/authorization?event='.$event->getId()->toRfc4122(), server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);
        self::assertContains($topic, (array) $this->json()['topics']);
        $this->client->getCookieJar()->clear();
        $this->login('alice@example.com', self::PASSWORD);
        $this->client->request('POST', '/api/realtime/authorization?event='.$event->getId()->toRfc4122(), server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);
        self::assertNotContains($topic, (array) $this->json()['topics']);
    }

    private function assign(ClubEvent $event, TeamAssignment $assignment): void
    {
        $this->entityManager()->persist(new EveningTeamMember($event, $assignment, $this->now()));
        $this->entityManager()->flush();
    }

    private function salesUri(ClubEvent $event): string
    {
        return "/api/events/{$event->getId()->toRfc4122()}/service/sales";
    }

    private function entranceUri(ClubEvent $event): string
    {
        return "/api/events/{$event->getId()->toRfc4122()}/service/entrance";
    }

    private function accountsUri(ClubEvent $event): string
    {
        return "/api/events/{$event->getId()->toRfc4122()}/accounts";
    }
}
