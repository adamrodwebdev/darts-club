<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Enum\Role;
use App\Tests\ApiTestCase;

final class TreasuryTest extends ApiTestCase
{
    private string $treasurerSecret = '';

    public function testOpenSeasonAndRecordPaymentsUpdatesStatusBadgeAndDashboard(): void
    {
        $alice = $this->createUser('alice@example.com');
        $this->createUser('bob@example.com');
        $this->createUser('pending@example.com', verified: false);
        $this->loginAsTreasurer();

        $this->postJson('/api/treasury/seasons/2026-2027/open', ['amountDue' => '30']);
        self::assertResponseStatusCodeSame(201);
        self::assertSame(3, $this->json()['created'], 'Alice, Bob et le trésorier, pas le compte non vérifié');

        $id = $this->membershipIdOf('alice');

        $this->postJson("/api/treasury/memberships/{$id}/payments", ['amount' => '10', 'method' => 'especes', 'paidAt' => '2026-09-20']);
        self::assertResponseStatusCodeSame(201);
        self::assertSame('partielle', $this->dig($this->json(), 'status', 'id'));
        self::assertEmailCount(0);

        $this->postJson("/api/treasury/memberships/{$id}/payments", ['amount' => '20,00', 'method' => 'virement', 'paidAt' => '2026-10-02', 'reference' => 'VIR-42']);
        self::assertSame('payee', $this->dig($this->json(), 'status', 'id'));

        // L'adhérente est prévenue
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailSubjectContains($email, '[Cotisation] Cotisation 2026-2027 réglée');

        $this->client->request('GET', '/api/treasury/dashboard');
        $dashboard = $this->json();
        self::assertSame('90.00', $dashboard['expected']);
        self::assertSame('30.00', $dashboard['collected']);
        self::assertSame(['payee' => 1, 'partielle' => 0, 'impayee' => 2], $dashboard['byStatus']);
        self::assertSame(2, $dashboard['overdue']);

        // Badge « cotisant » visible des autres adhérents
        $this->client->request('GET', '/api/members?q=alice');
        self::assertSame([['id' => 'cotisant', 'label' => 'Cotisant']], $this->dig($this->json(), 'items', 0, 'badges'));
        self::assertSame($alice->getId()->toRfc4122(), $this->dig($this->json(), 'items', 0, 'id'));
    }

    public function testCancelledPaymentNoLongerCounts(): void
    {
        $this->createUser('alice@example.com');
        $this->loginAsTreasurer();
        $this->postJson('/api/treasury/seasons/2026-2027/open', ['amountDue' => '30']);
        $id = $this->membershipIdOf('alice');

        $this->postJson("/api/treasury/memberships/{$id}/payments", ['amount' => '30', 'method' => 'cheque', 'paidAt' => '2026-09-10']);
        $paymentId = $this->digString($this->json(), 'payments', 0, 'id');

        $this->postJson("/api/treasury/payments/{$paymentId}/cancel", ['reason' => '']);
        self::assertResponseStatusCodeSame(422);

        $this->postJson("/api/treasury/payments/{$paymentId}/cancel", ['reason' => 'Chèque sans provision']);
        self::assertResponseIsSuccessful();
        self::assertSame('impayee', $this->dig($this->json(), 'status', 'id'));
        self::assertSame('0.00', $this->json()['paid']);

        // Chaque mouvement est au journal, avec qui l'a fait
        $trail = $this->auditTrail();
        self::assertSame(['season.open', 'payment.record', 'payment.cancel'], array_column($trail, 'action'));
        self::assertSame(['season' => '2026-2027', 'amount' => '30.00', 'reason' => 'Chèque sans provision'], $trail[2]['details']);
        self::assertSame('alice', $trail[2]['target']);
    }

    public function testInvalidPaymentsAreRejected(): void
    {
        $this->createUser('alice@example.com');
        $this->loginAsTreasurer();
        $this->postJson('/api/treasury/seasons/2026-2027/open', ['amountDue' => '30']);
        $id = $this->membershipIdOf('alice');

        foreach ([
            ['amount' => '0', 'method' => 'especes', 'paidAt' => '2026-09-10'],
            ['amount' => '-5', 'method' => 'especes', 'paidAt' => '2026-09-10'],
            ['amount' => '10', 'method' => 'bitcoin', 'paidAt' => '2026-09-10'],
            ['amount' => '10', 'method' => 'especes', 'paidAt' => '2027-01-01'],
        ] as $payload) {
            $this->postJson("/api/treasury/memberships/{$id}/payments", $payload);
            self::assertResponseStatusCodeSame(422, (string) json_encode($payload));
        }
    }

    public function testCsvExportNeutralizesFormulas(): void
    {
        $this->createUser('alice@example.com');
        $this->loginAsTreasurer();
        $this->postJson('/api/treasury/seasons/2026-2027/open', ['amountDue' => '30']);
        $id = $this->membershipIdOf('alice');
        $this->postJson("/api/treasury/memberships/{$id}/payments", ['amount' => '30', 'method' => 'cheque', 'paidAt' => '2026-09-10', 'reference' => '=HYPERLINK("http://evil")']);

        $this->client->request('GET', '/api/treasury/export?season=2026-2027');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Disposition', 'attachment; filename="cotisations-2026-2027.csv"');
        $csv = (string) $this->client->getResponse()->getContent();
        self::assertStringStartsWith("\u{FEFF}Saison;Adhérent;Email", $csv);
        self::assertStringContainsString('"\'=HYPERLINK(""http://evil"")"', $csv);
    }

    public function testRemindersAreSentToOverdueMembersOncePerWeek(): void
    {
        $this->createUser('alice@example.com');
        $this->createUser('bob@example.com');
        $this->loginAsTreasurer();
        $this->postJson('/api/treasury/seasons/2026-2027/open', ['amountDue' => '30']);
        $this->postJson('/api/treasury/memberships/'.$this->membershipIdOf('bob').'/payments', ['amount' => '30', 'method' => 'especes', 'paidAt' => '2026-09-10']);

        $this->postJson('/api/treasury/reminders', []);
        self::assertSame(2, $this->json()['sent'], 'Alice et le trésorier ; Bob est à jour');

        $this->postJson('/api/treasury/reminders', []);
        self::assertSame(0, $this->json()['sent']);
    }

    public function testMembersCannotAccessTreasuryButSeeTheirOwnMembership(): void
    {
        $this->createUser('alice@example.com');
        $this->loginAsTreasurer();
        $this->postJson('/api/treasury/seasons/2026-2027/open', ['amountDue' => '30']);

        $this->client->request('POST', '/api/auth/logout', server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);
        $this->login('alice@example.com', self::PASSWORD);

        $this->client->request('GET', '/api/treasury/memberships');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/api/me/membership');
        self::assertResponseIsSuccessful();
        self::assertSame('impayee', $this->dig($this->json(), 'current', 'status', 'id'));
        $holder = $this->dig($this->json(), 'current', 'holder');
        self::assertIsArray($holder);
        self::assertArrayNotHasKey('email', $holder);
    }

    public function testRoleBadgesFollowAssignedRolesOnly(): void
    {
        $president = $this->createUser('president@example.com');
        $president->grantRole(Role::President);
        $admin = $this->createUser('admin@example.com');
        $admin->grantRole(Role::Admin);
        $this->entityManager()->flush();
        $this->createUser('alice@example.com');
        $this->login('alice@example.com', self::PASSWORD);

        $this->client->request('GET', '/api/members');
        $items = $this->dig($this->json(), 'items');
        self::assertIsArray($items);
        $badges = [];
        foreach (array_keys($items) as $i) {
            $badges[$this->digString($items, $i, 'displayName')] = $this->dig($items, $i, 'badges');
        }

        self::assertSame([['id' => 'president', 'label' => 'Président']], $badges['president']);
        self::assertSame([], $badges['admin'], 'L\'admin technique n\'a pas de badge de fonction');
    }

    public function testDeletedMemberKeepsAccountingRecord(): void
    {
        $this->createUser('alice@example.com');
        $this->loginAsTreasurer();
        $this->postJson('/api/treasury/seasons/2026-2027/open', ['amountDue' => '30']);

        $this->client->request('POST', '/api/auth/logout', server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);
        $this->login('alice@example.com', self::PASSWORD);
        $this->client->request('DELETE', '/api/me', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => self::FRONT_ORIGIN], content: json_encode(['confirmation' => 'SUPPRIMER', 'password' => self::PASSWORD], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(204);

        $this->loginWithTwoFactor('treasurer@example.com', $this->treasurerSecret);
        self::assertContains('alice', $this->holderNames());
    }

    private function loginAsTreasurer(): User
    {
        $treasurer = $this->createUser('treasurer@example.com');
        $treasurer->grantRole(Role::Treasurer);
        $this->entityManager()->flush();
        $this->treasurerSecret = $this->enableTwoFactor($treasurer);
        $this->loginWithTwoFactor('treasurer@example.com', $this->treasurerSecret);

        return $treasurer;
    }

    /** @return array<string, string> nom affiché => identifiant de cotisation */
    private function memberships(): array
    {
        $this->client->request('GET', '/api/treasury/memberships');
        $items = $this->dig($this->json(), 'items');
        self::assertIsArray($items);

        $result = [];
        foreach (array_keys($items) as $i) {
            $result[$this->digString($items, $i, 'holder', 'displayName')] = $this->digString($items, $i, 'id');
        }

        return $result;
    }

    /** @return list<string> */
    private function holderNames(): array
    {
        return array_keys($this->memberships());
    }

    private function membershipIdOf(string $displayName): string
    {
        return $this->memberships()[$displayName] ?? self::fail("Cotisation de {$displayName} introuvable.");
    }
}
