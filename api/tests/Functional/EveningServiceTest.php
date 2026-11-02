<?php

namespace App\Tests\Functional;

use App\Entity\ClubEvent;
use App\Entity\EveningTeamMember;
use App\Enum\Role;
use App\Evening\Team;
use App\Evening\TeamAssignment;
use App\Tests\ApiTestCase;
use App\Tests\Double\MercureRecorder;

/**
 * Bar et cuisine d'une soirée (étape 13c) : commandes aux prix de la carte, stock décompté, plats
 * en cuisine, chaque étape validée une seule fois, annulation qui rend le stock, épuisés.
 * Horloge des tests figée au 15 octobre 2026, 12 h (UTC) : la soirée du jour est ouverte.
 */
final class EveningServiceTest extends ApiTestCase
{
    use BoardScenario;

    private const FIRST = '01a1b2c3-0000-7000-8000-00000000b001';
    private const SECOND = '01a1b2c3-0000-7000-8000-00000000b002';
    private const THIRD = '01a1b2c3-0000-7000-8000-00000000b003';

    private string $beer = '';
    private string $hotDog = '';

    public function testTheBarTakesAnOrderAndSendsTheFoodToTheKitchen(): void
    {
        $event = $this->eveningWithMenu();

        // Prix de la carte : un prix envoyé par l'appareil est ignoré
        $this->postJson($this->ordersUri($event), ['id' => self::FIRST, 'note' => 'Table 4', 'lines' => [
            ['itemId' => $this->beer, 'format' => 'verre', 'count' => 2, 'price' => '0.01'],
            ['itemId' => $this->hotDog, 'format' => 'Portion', 'count' => 1, 'garnishes' => ['Ketchup']],
        ]]);
        self::assertResponseStatusCodeSame(201);
        $waiting = (array) $this->dig($this->json(), 'orders', 'waiting', 0);
        self::assertSame([1, 'Table 4', '7.00', 'en_attente'], [$waiting['number'] ?? null, $waiting['note'] ?? null, $waiting['total'] ?? null, $waiting['kitchen'] ?? null]);
        self::assertSame(['Ketchup'], $this->dig($waiting, 'lines', 1, 'garnishes'));
        self::assertSame([2950, 1], [$this->dig($this->json(), 'menu', 0, 'left'), $this->dig($this->json(), 'menu', 1, 'left')]);
        self::assertSame(2, $this->json()['nextNumber']);
        $published = $this->service(MercureRecorder::class, MercureRecorder::class)->published;
        $update = json_decode($published[0]['data'] ?? '{}', true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($update);
        self::assertSame('order', $update['kind'] ?? null);

        // Renvoyée après une coupure : prise une seule fois, stock inchangé
        $this->postJson($this->ordersUri($event), ['id' => self::FIRST, 'lines' => [['itemId' => $this->beer, 'format' => 'Verre', 'count' => 2]]]);
        self::assertResponseStatusCodeSame(201);
        self::assertCount(1, (array) $this->dig($this->json(), 'orders', 'waiting'));
        self::assertSame(2950, $this->dig($this->json(), 'menu', 0, 'left'));

        // Boissons seules : servies au bar, rien pour la cuisine
        $this->postJson($this->ordersUri($event), ['id' => self::SECOND, 'lines' => [['itemId' => $this->beer, 'format' => 'Pichet', 'count' => 1]]]);
        self::assertCount(1, (array) $this->dig($this->json(), 'orders', 'waiting'));
        self::assertSame(2800, $this->dig($this->json(), 'menu', 0, 'left'));

        // Le bilan suit : 2 verres et 1 pichet vendus
        $this->client->request('GET', "/api/events/{$event->getId()->toRfc4122()}/accounts");
        self::assertSame([2, 1], array_column((array) $this->dig($this->json(), 'items', 0, 'formats'), 'sold'));
    }

    public function testEachKitchenStepIsConfirmedOnce(): void
    {
        $event = $this->eveningWithMenu();
        $sophie = $this->createUser('sophie@example.com');
        $marc = $this->createUser('marc@example.com');
        $this->assign($event, new TeamAssignment($sophie, Team::Kitchen));
        $this->assign($event, new TeamAssignment($marc, Team::Bar));
        $this->postJson($this->ordersUri($event), ['id' => self::FIRST, 'lines' => [['itemId' => $this->hotDog, 'format' => 'Portion', 'count' => 1]]]);

        $this->switchTo('sophie@example.com');
        $this->client->request('GET', "/api/events/{$event->getId()->toRfc4122()}/service/kitchen");
        self::assertSame([1], array_column((array) $this->json()['pending'], 'number'));
        $this->postJson('/api/evening-orders/'.self::FIRST.'/deliver', []);
        self::assertResponseStatusCodeSame(403, 'La cuisine ne remet pas au client');
        $this->postJson('/api/evening-orders/'.self::FIRST.'/ready', []);
        self::assertResponseIsSuccessful();
        self::assertSame([], $this->json()['pending']);
        self::assertSame('prete', $this->dig($this->json(), 'sent', 0, 'kitchen'));
        // Deuxième tablette, même commande : « déjà prête »
        $this->postJson('/api/evening-orders/'.self::FIRST.'/ready', []);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('Commande déjà prête.', $this->json()['error'] ?? $this->json()['detail'] ?? null);

        $this->switchTo('marc@example.com');
        $this->postJson('/api/evening-orders/'.self::FIRST.'/ready', []);
        self::assertResponseStatusCodeSame(403, 'Le bar ne prépare pas les plats');
        $this->postJson('/api/evening-orders/'.self::FIRST.'/deliver', []);
        self::assertResponseIsSuccessful();
        $delivered = (array) $this->dig($this->json(), 'orders', 'delivered', 0);
        self::assertSame([1, 'remise', 'marc'], [$delivered['number'] ?? null, $delivered['kitchen'] ?? null, $delivered['deliveredBy'] ?? null]);
        $this->postJson('/api/evening-orders/'.self::FIRST.'/deliver', []);
        self::assertResponseStatusCodeSame(409, 'Remise une seule fois : jamais servie deux fois');
    }

    public function testACancelledOrderGivesTheFoodBackToTheStock(): void
    {
        $event = $this->eveningWithMenu();
        $this->postJson($this->ordersUri($event), ['id' => self::FIRST, 'lines' => [
            ['itemId' => $this->beer, 'format' => 'Verre', 'count' => 1],
            ['itemId' => $this->hotDog, 'format' => 'Portion', 'count' => 1],
        ]]);
        self::assertSame(1, $this->dig($this->json(), 'menu', 1, 'left'));

        $this->postJson('/api/evening-orders/'.self::FIRST.'/cancel', []);
        self::assertResponseIsSuccessful();
        // Les plats reviennent, la boisson déjà servie reste vendue
        self::assertSame([2975, 2], [$this->dig($this->json(), 'menu', 0, 'left'), $this->dig($this->json(), 'menu', 1, 'left')]);
        self::assertSame([], $this->dig($this->json(), 'orders', 'waiting'));
        self::assertSame(['service.order_cancel'], \array_slice(array_column($this->auditTrail(), 'action'), -1));

        $this->postJson('/api/evening-orders/'.self::FIRST.'/cancel', []);
        self::assertResponseStatusCodeSame(409);
        $this->postJson('/api/evening-orders/'.self::FIRST.'/deliver', []);
        self::assertResponseStatusCodeSame(409);
    }

    public function testSoldOutItemsAndToppingsAreRefused(): void
    {
        $event = $this->eveningWithMenu();
        $this->request('PUT', "/api/event-accounts/items/{$this->hotDog}/availability", ['soldOut' => false, 'garnishesOut' => ['Moutarde']]);
        self::assertResponseIsSuccessful();
        self::assertSame([false, true], array_column((array) $this->dig($this->json(), 'stock', 0, 'garnishes'), 'out'));

        $this->postJson($this->ordersUri($event), ['id' => self::FIRST, 'lines' => [['itemId' => $this->hotDog, 'format' => 'Portion', 'count' => 1, 'garnishes' => ['Moutarde']]]]);
        self::assertResponseStatusCodeSame(422);

        // Plus que deux hot dogs : la troisième demande est refusée, l'article s'affiche épuisé
        $this->postJson($this->ordersUri($event), ['id' => self::SECOND, 'lines' => [['itemId' => $this->hotDog, 'format' => 'Portion', 'count' => 2]]]);
        self::assertResponseStatusCodeSame(201);
        self::assertTrue($this->dig($this->json(), 'menu', 1, 'soldOut'));
        $this->postJson($this->ordersUri($event), ['id' => self::THIRD, 'lines' => [['itemId' => $this->hotDog, 'format' => 'Portion', 'count' => 1]]]);
        self::assertResponseStatusCodeSame(422);

        // Fût déclaré vide par le bar avant le compte
        $this->request('PUT', "/api/event-accounts/items/{$this->beer}/availability", ['soldOut' => true, 'garnishesOut' => []]);
        $this->postJson($this->ordersUri($event), ['id' => self::THIRD, 'lines' => [['itemId' => $this->beer, 'format' => 'Verre', 'count' => 1]]]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testEditingTheMenuDuringTheEveningKeepsTheSalesCountedByOrders(): void
    {
        $event = $this->eveningWithMenu();
        $this->postJson($this->ordersUri($event), ['id' => self::FIRST, 'lines' => [['itemId' => $this->beer, 'format' => 'Verre', 'count' => 3]]]);

        // Formulaire ouvert avant la commande : il envoie encore 0 vendu
        $this->request('PUT', "/api/event-accounts/items/{$this->beer}", self::beerPayload(price: '2.50'));
        self::assertResponseIsSuccessful();
        self::assertSame([[3, '2.50'], [0, '10.00']], array_map(static fn (mixed $f): array => [((array) $f)['sold'] ?? null, ((array) $f)['price'] ?? null], (array) $this->dig($this->json(), 'items', 0, 'formats')));
    }

    /** Soirée du jour : un fût de 30 L (verre 25 cl à 2 €, pichet 1,5 L à 10 €), deux hot dogs à 3 € */
    private function eveningWithMenu(): ClubEvent
    {
        $event = $this->evening('2026-10-15 20:00');
        $this->board(['president@example.com' => Role::President]);
        $this->loginAs('president@example.com');
        $items = "/api/events/{$event->getId()->toRfc4122()}/accounts/items";
        $this->postJson($items, self::beerPayload());
        self::assertResponseStatusCodeSame(201);
        $this->postJson($items, ['label' => 'Hot dog', 'station' => 'cuisine', 'unit' => 'portion', 'garnishes' => ['Ketchup', 'Moutarde'], 'containerSize' => 1,
            'planned' => 2, 'bought' => 2, 'returned' => 0, 'purchasePrice' => '0.90', 'formats' => [['name' => 'Portion', 'volume' => 1, 'price' => '3', 'sold' => 0]]]);
        self::assertResponseStatusCodeSame(201);
        $this->beer = $this->digString($this->json(), 'items', 0, 'id');
        $this->hotDog = $this->digString($this->json(), 'items', 1, 'id');

        return $event;
    }

    /** @return array<string, mixed> */
    private static function beerPayload(string $price = '2'): array
    {
        return ['label' => 'Bière pression', 'station' => 'bar', 'unit' => 'litre', 'alcoholic' => true, 'containerName' => 'Fût', 'containerSize' => 3000,
            'planned' => 1, 'bought' => 1, 'returned' => 0, 'purchasePrice' => '95',
            'formats' => [['name' => 'Verre', 'volume' => 25, 'price' => $price, 'sold' => 0], ['name' => 'Pichet', 'volume' => 150, 'price' => '10', 'sold' => 0]]];
    }

    private function switchTo(string $email): void
    {
        $this->client->getCookieJar()->clear();
        $this->login($email, self::PASSWORD);
    }

    /** Soirée relue : après une requête, le gestionnaire d'entités du test est neuf */
    private function assign(ClubEvent $event, TeamAssignment $assignment): void
    {
        $em = $this->entityManager();
        $em->persist(new EveningTeamMember($em->find(ClubEvent::class, $event->getId()) ?? $event, $assignment, $this->now()));
        $em->flush();
    }

    private function ordersUri(ClubEvent $event): string
    {
        return "/api/events/{$event->getId()->toRfc4122()}/service/orders";
    }
}
