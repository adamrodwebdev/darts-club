<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\ClubEvent;
use App\Entity\EveningTeamMember;
use App\Enum\Role;
use App\Evening\Team;
use App\Evening\TeamAssignment;
use App\Tests\ApiTestCase;

/**
 * Recettes en direct d'une soirée (étape 13d) : argent de l'accueil, jetons dépensés au bar et en
 * cuisine aux prix des commandes, jetons en circulation, ventes par article ; le bilan compte
 * l'argent de l'accueil ; export CSV. Horloge des tests figée au 15 octobre 2026, 12 h (UTC).
 */
final class LiveIncomeTest extends ApiTestCase
{
    use BoardScenario;

    private const SALE_CANCELLED = '01a1b2c3-0000-7000-8000-00000000d003';
    private const ORDER_CANCELLED = '01a1b2c3-0000-7000-8000-00000000e003';

    private string $beer = '';
    private string $hotDog = '';

    public function testTheBoardFollowsTheEveningsIncome(): void
    {
        $event = $this->busyEvening();

        $this->client->request('GET', $this->incomeUri($event));
        self::assertResponseIsSuccessful();
        $income = $this->json();
        self::assertTrue($income['tokensEnabled']);
        self::assertSame(
            ['cash' => '20.00', 'card' => '6.00', 'total' => '26.00', 'cashFloat' => '50.00', 'expectedCash' => '70.00'],
            array_diff_key((array) $income['entrance'], ['tokens' => true]),
        );
        self::assertSame(
            [['Sachet', 2, '20.00'], ['Jeton vert', 5, '5.00'], ['Jeton jaune', 2, '1.00']],
            array_map(static fn (mixed $t): array => [((array) $t)['name'] ?? null, ((array) $t)['count'] ?? null, ((array) $t)['amount'] ?? null], (array) $this->dig($income, 'entrance', 'tokens')),
        );
        // Boissons de la commande annulée servies ; son hot dog rendu au client
        self::assertSame(['amount' => '14.00', 'served' => 3], $income['bar']);
        self::assertSame(['amount' => '3.00', 'served' => 1], $income['kitchen']);
        self::assertSame(['17.00', '9.00'], [$income['spent'], $income['circulation']]);
        self::assertSame(['count' => 3, 'waiting' => 1, 'ready' => 0, 'cancelled' => 1], $income['orders']);

        $beer = (array) $this->dig($income, 'items', 0);
        self::assertSame([$this->beer, 'litre', '14.00', 200, 3000, 2800], [$beer['id'] ?? null, $beer['unit'] ?? null, $beer['amount'] ?? null, $beer['served'] ?? null, $beer['stock'] ?? null, $beer['left'] ?? null]);
        self::assertSame(
            [['Verre', 25, 2, '4.00'], ['Pichet', 150, 1, '10.00']],
            array_map(static fn (mixed $f): array => array_values((array) $f), (array) $beer['formats']),
        );
        $hotDog = (array) $this->dig($income, 'items', 1);
        self::assertSame(['3.00', 1, 2, 1], [$hotDog['amount'] ?? null, $hotDog['served'] ?? null, $hotDog['stock'] ?? null, $hotDog['left'] ?? null]);
    }

    public function testTheAccountsCountTheEntranceMoneyOnceTokensAreSold(): void
    {
        $event = $this->busyEvening();

        $this->client->request('GET', "/api/events/{$event->getId()->toRfc4122()}/accounts");
        $totals = (array) $this->json()['totals'];
        // Achats : fût 95 € et deux hot dogs à 0,90 € ; ventes de la carte 17 €, accueil 26 €
        self::assertSame(
            ['entrance', '26.00', '17.00', '96.80', '26.00', '-70.80'],
            [$totals['barIncomeSource'] ?? null, $totals['barIncome'] ?? null, $totals['barSales'] ?? null, $totals['costs'] ?? null, $totals['income'] ?? null, $totals['result'] ?? null],
        );

        $this->client->request('GET', "/api/events/{$event->getId()->toRfc4122()}/accounts/export.csv");
        $csv = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Accueil;"Argent encaissé à l\'accueil (compté à la place des ventes de la buvette)";;;;;;;;;;;26.00', $csv);
        // Prévu : 120 verres à 2 € et deux hot dogs à 3 €, moins les 96,80 € d'achats
        self::assertStringContainsString('Résultat;;;;;;;;;;;149.20;-70.80', $csv);

        // Le budget des démarches aussi : une ligne pour l'accueil à la place des ventes par article
        $this->client->request('GET', "/api/events/{$event->getId()->toRfc4122()}/procedures/budget");
        self::assertResponseIsSuccessful();
        $lines = array_column(array_filter((array) $this->json()['blocks'], static fn (mixed $block): bool => \is_array($block) && 'ligne' === $block['kind']), 'text', 'label');
        $euro = "\u{00A0}€";
        self::assertSame("prévu 246,00{$euro} · réel 26,00{$euro}", $lines['Buvette · jetons vendus à l’accueil'] ?? null);
        self::assertArrayNotHasKey('Ventes buvette · Bière pression', $lines);
        self::assertSame("prévu +149,20{$euro} · réel −70,80{$euro}", $lines['Résultat'] ?? null);
    }

    public function testWithoutEntranceSalesTheAccountsKeepTheMenuSales(): void
    {
        $event = $this->evening('2026-10-15 20:00');
        $this->board(['president@example.com' => Role::President]);
        $this->loginAs('president@example.com');
        $this->addMenu($event);

        $this->client->request('GET', "/api/events/{$event->getId()->toRfc4122()}/accounts");
        self::assertSame(['menu', '0.00', '0.00'], [$this->dig($this->json(), 'totals', 'barIncomeSource'), $this->dig($this->json(), 'totals', 'barIncome'), $this->dig($this->json(), 'totals', 'barSales')]);
        $this->client->request('GET', $this->incomeUri($event));
        self::assertSame(['0.00', '0.00', 0], [$this->dig($this->json(), 'entrance', 'total'), $this->dig($this->json(), 'circulation'), $this->dig($this->json(), 'orders', 'count')]);
        self::assertSame([0, 0], array_column((array) $this->dig($this->json(), 'items', 0, 'formats'), 'count'));
    }

    public function testTheIncomeExportsToASpreadsheet(): void
    {
        $event = $this->busyEvening();

        $this->client->request('GET', $this->incomeUri($event).'.csv');
        self::assertResponseIsSuccessful();
        self::assertStringStartsWith('text/csv', (string) $this->client->getResponse()->headers->get('Content-Type'));
        self::assertStringContainsString('attachment; filename="recettes-2026-10-15.csv"', (string) $this->client->getResponse()->headers->get('Content-Disposition'));
        $csv = (string) $this->client->getResponse()->getContent();
        self::assertStringStartsWith("\u{FEFF}Rubrique;Libellé;Nombre;Montant\n", $csv);
        foreach ([
            'Accueil;Espèces;;20.00',
            'Accueil;Sachet;2;20.00',
            'Accueil;"Jeton vert";5;5.00',
            'Accueil;"Attendu en caisse (fond + espèces)";;70.00',
            'Bar;"Bière pression · Verre";2;4.00',
            'Bar;"Bière pression · Pichet";1;10.00',
            'Cuisine;"Hot dog · Portion";1;3.00',
            'Total;"Jetons en circulation (vendus moins dépensés)";;9.00',
            'Total;"Commandes annulées (part cuisine rendue)";1;',
        ] as $line) {
            self::assertStringContainsString($line, $csv);
        }
    }

    public function testOnlyTheBoardSeesTheIncome(): void
    {
        $event = $this->busyEvening();
        $marc = $this->createUser('marc@example.com');
        $em = $this->entityManager();
        $em->persist(new EveningTeamMember($em->find(ClubEvent::class, $event->getId()) ?? $event, new TeamAssignment($marc, Team::Bar), $this->now()));
        $em->flush();

        $this->client->getCookieJar()->clear();
        $this->client->request('GET', $this->incomeUri($event));
        self::assertResponseStatusCodeSame(401);

        $this->login('marc@example.com', self::PASSWORD);
        $this->client->request('GET', $this->incomeUri($event));
        self::assertResponseStatusCodeSame(403, 'Le bar de la soirée ne voit pas les recettes');
        $this->client->request('GET', $this->incomeUri($event).'.csv');
        self::assertResponseStatusCodeSame(403);
    }

    /**
     * Soirée du jour : fond de caisse 50 € ; accueil 20 € en espèces et 6 € par carte (une vente de
     * 10 € annulée) ; bar 2 verres et 1 pichet ; cuisine 1 hot dog servi, 1 annulé.
     */
    private function busyEvening(): ClubEvent
    {
        $event = $this->evening('2026-10-15 20:00');
        $this->board(['president@example.com' => Role::President]);
        $this->loginAs('president@example.com');
        $this->addMenu($event);
        $id = $event->getId()->toRfc4122();
        $this->request('PUT', "/api/events/{$id}/accounts/cash", ['tokensEnabled' => true, 'cashFloat' => '50', 'tokens' => [
            ['name' => 'Jeton vert', 'value' => '1', 'bag' => false, 'color' => 'vert'],
            ['name' => 'Jeton jaune', 'value' => '0.50', 'bag' => false, 'color' => 'jaune'],
            ['name' => 'Sachet', 'value' => '10', 'bag' => true],
        ]]);
        self::assertResponseIsSuccessful();

        $sales = "/api/events/{$id}/service/sales";
        $this->postJson($sales, ['id' => '01a1b2c3-0000-7000-8000-00000000d001', 'method' => 'especes', 'lines' => [['name' => 'Sachet', 'count' => 2]]]);
        $this->postJson($sales, ['id' => '01a1b2c3-0000-7000-8000-00000000d002', 'method' => 'carte', 'lines' => [['name' => 'Jeton vert', 'count' => 5], ['name' => 'Jeton jaune', 'count' => 2]]]);
        $this->postJson($sales, ['id' => self::SALE_CANCELLED, 'method' => 'especes', 'lines' => [['name' => 'Sachet', 'count' => 1]]]);
        self::assertResponseStatusCodeSame(201);
        $this->postJson('/api/token-sales/'.self::SALE_CANCELLED.'/cancel', []);
        self::assertResponseIsSuccessful();

        $orders = "/api/events/{$id}/service/orders";
        $this->postJson($orders, ['id' => '01a1b2c3-0000-7000-8000-00000000e001', 'lines' => [
            ['itemId' => $this->beer, 'format' => 'Verre', 'count' => 2],
            ['itemId' => $this->hotDog, 'format' => 'Portion', 'count' => 1],
        ]]);
        $this->postJson($orders, ['id' => '01a1b2c3-0000-7000-8000-00000000e002', 'lines' => [['itemId' => $this->beer, 'format' => 'Pichet', 'count' => 1]]]);
        $this->postJson($orders, ['id' => self::ORDER_CANCELLED, 'lines' => [['itemId' => $this->hotDog, 'format' => 'Portion', 'count' => 1]]]);
        self::assertResponseStatusCodeSame(201);
        $this->postJson('/api/evening-orders/'.self::ORDER_CANCELLED.'/cancel', []);
        self::assertResponseIsSuccessful();

        return $event;
    }

    /** Un fût de 30 L (verre 25 cl à 2 €, pichet 1,5 L à 10 €) et deux hot dogs à 3 € */
    private function addMenu(ClubEvent $event): void
    {
        $items = "/api/events/{$event->getId()->toRfc4122()}/accounts/items";
        $this->postJson($items, ['label' => 'Bière pression', 'station' => 'bar', 'unit' => 'litre', 'alcoholic' => true, 'containerName' => 'Fût', 'containerSize' => 3000,
            'planned' => 1, 'bought' => 1, 'returned' => 0, 'purchasePrice' => '95',
            'formats' => [['name' => 'Verre', 'volume' => 25, 'price' => '2', 'sold' => 0], ['name' => 'Pichet', 'volume' => 150, 'price' => '10', 'sold' => 0]]]);
        self::assertResponseStatusCodeSame(201);
        $this->postJson($items, ['label' => 'Hot dog', 'station' => 'cuisine', 'unit' => 'portion', 'garnishes' => [], 'containerSize' => 1,
            'planned' => 2, 'bought' => 2, 'returned' => 0, 'purchasePrice' => '0.90', 'formats' => [['name' => 'Portion', 'volume' => 1, 'price' => '3', 'sold' => 0]]]);
        self::assertResponseStatusCodeSame(201);
        $this->beer = $this->digString($this->json(), 'items', 0, 'id');
        $this->hotDog = $this->digString($this->json(), 'items', 1, 'id');
    }

    private function incomeUri(ClubEvent $event): string
    {
        return "/api/events/{$event->getId()->toRfc4122()}/accounts/income";
    }
}
