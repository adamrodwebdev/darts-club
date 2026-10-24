<?php

namespace App\Tests\Functional;

use App\ClubEvent\EventDetails;
use App\Entity\ClubEvent;
use App\Enum\Role;
use App\EventAccounts\PurgeOldReceipts;
use App\EventAccounts\PurgeOldReceiptsHandler;
use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Bilan d'une soirée : saisie par la secrétaire et le trésorier, lecture par le bureau, justificatifs privés. */
final class EventAccountsTest extends ApiTestCase
{
    /** @var array<string, string> secrets TOTP des membres du bureau */
    private array $secrets = [];

    private int $organizers = 0;

    public function testTheSecretaryKeepsTheEveningAccountsAndTheTotalsAddUp(): void
    {
        $event = $this->evening('2026-11-14 20:00');
        $this->board(['secretary@example.com' => Role::Secretary]);
        $this->loginAs('secretary@example.com');
        $uri = $this->accountsUri($event);

        // Chiffres de la maquette validée : buvette, autres dépenses et recettes
        foreach ([
            ['Bière 33 cl', 120, 144, 24, 112, '0,95', '3,00'],
            ['Soda (canette)', 60, 72, 0, 58, '0,55', '1,50'],
            ['Eau 50 cl', 48, 48, 12, 30, '0,30', '1,00'],
            ['Hot-dog', 80, 80, 0, 71, '0,90', '3,00'],
            ['Chips', 40, 40, 0, 33, '0,45', '1,00'],
        ] as [$label, $planned, $bought, $returned, $sold, $purchase, $sale]) {
            $this->postJson($uri.'/items', compact('label', 'planned', 'bought', 'returned', 'sold') + ['purchasePrice' => $purchase, 'salePrice' => $sale]);
            self::assertResponseStatusCodeSame(201);
        }
        foreach ([
            ['depense', 'Location de la salle', '80', '80'],
            ['depense', 'SACEM', '46,20', '46,20'],
            ['depense', 'Affiches et billets', '32', '32'],
            ['recette', 'Participation aux frais', '100', '96'],
            ['recette', 'Dons', '0', '20'],
        ] as [$kind, $label, $planned, $actual]) {
            $this->postJson($uri.'/entries', compact('kind', 'label', 'planned', 'actual'));
            self::assertResponseStatusCodeSame(201);
        }

        self::assertSame([
            'barCosts' => '254.40', 'barIncome' => '699.00', 'costs' => '412.60', 'income' => '815.00', 'result' => '402.40',
            'plannedCosts' => '409.60', 'plannedIncome' => '878.00', 'plannedResult' => '468.40',
        ], $this->json()['totals']);
        self::assertSame(['label' => 'Bière 33 cl', 'left' => 8, 'cost' => '114.00', 'income' => '336.00'], array_intersect_key((array) $this->dig($this->json(), 'items', 0), array_flip(['label', 'left', 'cost', 'income'])));
        self::assertSame(['edit' => true, 'reopen' => false], $this->json()['permissions']);
        $actions = array_column($this->auditTrail(), 'action');
        self::assertSame([...array_fill(0, 5, 'accounts.item_add'), ...array_fill(0, 5, 'accounts.entry_add')], $actions);

        // Quantités incohérentes : refusées, avec la règle en clair
        $this->postJson($uri.'/items', ['label' => 'Cidre', 'planned' => 10, 'bought' => 10, 'returned' => 12, 'sold' => 0, 'purchasePrice' => '2', 'salePrice' => '3']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('On ne peut pas rendre plus que ce qui a été acheté.', $this->json()['error'] ?? $this->json()['detail'] ?? null);
        $this->postJson($uri.'/items', ['label' => 'Cidre', 'planned' => 10, 'bought' => 10, 'returned' => 2, 'sold' => 9, 'purchasePrice' => '2', 'salePrice' => '3']);
        self::assertResponseStatusCodeSame(422);

        // Correction d'un article, puis retrait d'une ligne
        $this->client->request('GET', $uri);
        $beer = $this->digString($this->json(), 'items', 0, 'id');
        $this->request('PUT', "/api/event-accounts/items/{$beer}", ['label' => 'Bière 33 cl', 'planned' => 120, 'bought' => 144, 'returned' => 24, 'sold' => 120, 'purchasePrice' => '0,95', 'salePrice' => '3']);
        self::assertResponseIsSuccessful();
        self::assertSame('426.40', $this->dig($this->json(), 'totals', 'result'));
        $donation = $this->digString($this->json(), 'entries', 4, 'id');
        $this->request('DELETE', "/api/event-accounts/entries/{$donation}");
        self::assertResponseIsSuccessful();
        self::assertCount(4, (array) $this->json()['entries']);
    }

    public function testThePresidencyReadsWithoutWritingAndMembersSeeNothing(): void
    {
        $event = $this->evening('2026-11-14 20:00');
        $this->board(['president@example.com' => Role::President]);
        $this->createUser('alice@example.com');
        $uri = $this->accountsUri($event);

        $this->loginAs('president@example.com');
        $this->client->request('GET', $uri);
        self::assertResponseIsSuccessful();
        self::assertSame(['edit' => false, 'reopen' => false], $this->json()['permissions']);
        $this->postJson($uri.'/items', ['label' => 'Bière', 'planned' => 1, 'bought' => 1, 'returned' => 0, 'sold' => 1, 'purchasePrice' => '1', 'salePrice' => '2']);
        self::assertResponseStatusCodeSame(403);

        $this->client->getCookieJar()->clear();
        $this->login('alice@example.com', self::PASSWORD);
        $this->client->request('GET', $uri);
        self::assertResponseStatusCodeSame(403);
    }

    public function testClosedAccountsAreLockedUntilTheTreasurerReopensThem(): void
    {
        $event = $this->evening('2026-11-14 20:00');
        $this->board(['secretary@example.com' => Role::Secretary, 'treasurer@example.com' => Role::Treasurer]);
        $uri = $this->accountsUri($event);
        $item = ['label' => 'Bière', 'planned' => 1, 'bought' => 1, 'returned' => 0, 'sold' => 1, 'purchasePrice' => '1', 'salePrice' => '2'];

        $this->loginAs('secretary@example.com');
        $this->postJson($uri.'/close', []);
        self::assertResponseIsSuccessful();
        self::assertTrue($this->json()['closed']);
        $this->postJson($uri.'/items', $item);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('Bilan clôturé : le trésorier peut le rouvrir.', $this->json()['error'] ?? $this->json()['detail'] ?? null);
        $this->postJson($uri.'/reopen', []);
        self::assertResponseStatusCodeSame(403);

        $this->client->getCookieJar()->clear();
        $this->loginAs('treasurer@example.com');
        $this->postJson($uri.'/reopen', []);
        self::assertResponseIsSuccessful();
        $this->postJson($uri.'/items', $item);
        self::assertResponseStatusCodeSame(201);
        self::assertSame(['accounts.close', 'accounts.reopen', 'accounts.item_add'], array_column($this->auditTrail(), 'action'));
    }

    public function testReceiptsAreStoredPrivatelyAndServedToTheBoardOnly(): void
    {
        $event = $this->evening('2026-11-14 20:00');
        $this->board(['secretary@example.com' => Role::Secretary]);
        $this->createUser('alice@example.com');
        $this->loginAs('secretary@example.com');
        $uri = $this->accountsUri($event);

        // Photo : réencodée en WebP ; PDF : gardé tel quel, toujours téléchargé
        $this->uploadReceipt($uri, $this->copyOf(__DIR__.'/../Fixtures/avatar.jpg'), ['label' => 'Ticket supermarché', 'amount' => '144,00']);
        self::assertResponseStatusCodeSame(201);
        $this->uploadReceipt($uri, $this->fileWith("%PDF-1.4\n%fin\n"), ['label' => 'Avoir retours', 'amount' => '26,40', 'credit' => '1']);
        self::assertResponseStatusCodeSame(201);
        $receipts = (array) $this->json()['receipts'];
        self::assertSame(['image', 'pdf'], array_column($receipts, 'type'));
        self::assertSame(['144.00', '26.40'], array_column($receipts, 'amount'));
        self::assertSame([false, true], array_column($receipts, 'credit'));
        $photo = $this->digString($this->json(), 'receipts', 0, 'id');
        $pdf = $this->digString($this->json(), 'receipts', 1, 'id');

        $this->client->request('GET', "/api/event-accounts/receipts/{$photo}");
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/webp');
        $this->client->request('GET', "/api/event-accounts/receipts/{$pdf}");
        self::assertResponseIsSuccessful();
        self::assertStringStartsWith('attachment;', (string) $this->client->getResponse()->headers->get('Content-Disposition'));

        // Ni un autre fichier, ni un adhérent
        $this->uploadReceipt($uri, $this->fileWith('pas un justificatif'), ['label' => 'Notes']);
        self::assertResponseStatusCodeSame(422);
        $files = $this->storedReceipts();
        self::assertCount(2, $files);

        $this->client->getCookieJar()->clear();
        $this->login('alice@example.com', self::PASSWORD);
        $this->client->request('GET', "/api/event-accounts/receipts/{$photo}");
        self::assertResponseStatusCodeSame(403);

        // Retiré : le fichier part avec sa ligne
        $this->client->getCookieJar()->clear();
        $this->loginAs('secretary@example.com');
        $this->request('DELETE', "/api/event-accounts/receipts/{$photo}");
        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->storedReceipts());
        $this->request('DELETE', "/api/event-accounts/receipts/{$pdf}");
        self::assertSame([], $this->storedReceipts());
    }

    public function testTheYearCountsTemporaryBarsAndTaxExemptEveningsSeparately(): void
    {
        $november = $this->evening('2026-11-14 20:00');
        $december = $this->evening('2026-12-12 20:00');
        $nextYear = $this->evening('2027-01-16 20:00');
        $this->board(['secretary@example.com' => Role::Secretary]);
        $this->loginAs('secretary@example.com');

        $this->request('PUT', $this->accountsUri($november).'/flags', ['temporaryBar' => true, 'taxExempt' => true]);
        $this->request('PUT', $this->accountsUri($december).'/flags', ['temporaryBar' => true, 'taxExempt' => false]);
        $this->request('PUT', $this->accountsUri($nextYear).'/flags', ['temporaryBar' => true, 'taxExempt' => true]);

        $this->client->request('GET', $this->accountsUri($december));
        self::assertSame(['year' => 2026, 'temporaryBars' => ['used' => 2, 'limit' => 5], 'taxExempt' => ['used' => 1, 'limit' => 6]], $this->json()['counters']);
    }

    public function testTheAccountsExportToASpreadsheet(): void
    {
        $event = $this->evening('2026-11-14 20:00');
        $this->board(['treasurer@example.com' => Role::Treasurer]);
        $this->loginAs('treasurer@example.com');
        $uri = $this->accountsUri($event);
        $this->postJson($uri.'/items', ['label' => '=Bière', 'planned' => 10, 'bought' => 10, 'returned' => 0, 'sold' => 8, 'purchasePrice' => '1', 'salePrice' => '3']);

        $this->client->request('GET', $uri.'/export.csv');
        self::assertResponseIsSuccessful();
        $csv = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Rubrique;Libellé;Prévu;Acheté;Rendu;Vendu;Reste', $csv);
        // Pas de formule exécutée par le tableur
        self::assertStringContainsString("Buvette;'=Bière;10;10;0;8;2;1.00;3.00;10.00;10.00;30.00;24.00", $csv);
        self::assertStringContainsString('Résultat;;;;;;;;;;;20.00;14.00', $csv);
    }

    public function testReceiptsAreErasedSixYearsAfterTheEvening(): void
    {
        $old = $this->evening('2019-06-15 20:00');
        $recent = $this->evening('2026-06-13 20:00');
        $this->board(['secretary@example.com' => Role::Secretary]);
        $this->loginAs('secretary@example.com');
        foreach ([$old, $recent] as $event) {
            $this->uploadReceipt($this->accountsUri($event), $this->fileWith("%PDF-1.4\n"), ['label' => 'Facture']);
            self::assertResponseStatusCodeSame(201);
        }

        $this->service(PurgeOldReceiptsHandler::class, PurgeOldReceiptsHandler::class)(new PurgeOldReceipts());

        $this->client->request('GET', $this->accountsUri($old));
        self::assertSame([], $this->json()['receipts']);
        $this->client->request('GET', $this->accountsUri($recent));
        self::assertCount(1, (array) $this->json()['receipts']);
        self::assertCount(1, $this->storedReceipts());
        $this->request('DELETE', '/api/event-accounts/receipts/'.$this->digString($this->json(), 'receipts', 0, 'id'));
    }

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

    private function accountsUri(ClubEvent $event): string
    {
        return "/api/events/{$event->getId()->toRfc4122()}/accounts";
    }

    /** @param array<string, string> $fields */
    private function uploadReceipt(string $uri, string $path, array $fields): void
    {
        $this->client->request('POST', $uri.'/receipts', $fields, ['file' => new UploadedFile($path, 'justificatif', test: true)], ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);
    }

    private function copyOf(string $fixture): string
    {
        $copy = tempnam(sys_get_temp_dir(), 'receipt');
        self::assertIsString($copy);
        copy($fixture, $copy);

        return $copy;
    }

    private function fileWith(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'receipt');
        self::assertIsString($path);
        file_put_contents($path, $content);

        return $path;
    }

    /** @param array<string, mixed> $payload */
    private function request(string $method, string $uri, array $payload = []): void
    {
        $this->client->request($method, $uri, server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => self::FRONT_ORIGIN], content: json_encode($payload, \JSON_THROW_ON_ERROR));
    }

    /** @return list<string> */
    private function storedReceipts(): array
    {
        $root = self::getContainer()->getParameter('kernel.project_dir');
        self::assertIsString($root);
        $files = glob($root.'/var/storage/receipts/*');

        return false === $files ? [] : $files;
    }
}
