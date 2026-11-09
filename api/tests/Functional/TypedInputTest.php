<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Enum\Role;
use App\Tests\ApiTestCase;

/**
 * Règle du 2026-11-09 : la donnée est vérifiée à la porte. Un texte est nettoyé avant d'être
 * gardé, refusé au-delà de sa longueur (jamais coupé) ; un nombre envoyé en texte est refusé.
 */
final class TypedInputTest extends ApiTestCase
{
    public function testAMessageIsCleanedBeforeItIsStoredAndKeepsItsLines(): void
    {
        $this->createUser('alice@example.com');
        $bob = $this->createUser('bob@example.com');
        $this->login('alice@example.com', self::PASSWORD);

        $this->postJson('/api/conversations', [
            'recipientId' => $bob->getId()->toRfc4122(),
            'body' => "Partie\u{202E} samedi\u{0000} ?\r\nOui\u{200B} !",
        ]);
        self::assertResponseStatusCodeSame(201);
        $id = $this->digString($this->json(), 'id');

        $this->client->request('GET', "/api/conversations/{$id}/messages");
        self::assertSame("Partie samedi ?\nOui !", $this->digString($this->json(), 'items', 0, 'body'));
    }

    public function testAnEventTitleIsCleanedOnOneLineAndItsDescriptionKeepsItsLines(): void
    {
        $this->loginAsSecretary();

        $this->postJson('/api/events', self::event([
            'title' => " Tournoi\u{2066} d'automne\r\n\u{FEFF}",
            'description' => "Format 501\r\nInscriptions sur place.\u{0007}",
        ]));
        self::assertResponseStatusCodeSame(201);
        self::assertSame('Tournoi d\'automne', $this->json()['title']);

        $this->client->request('GET', '/api/events/'.$this->digString($this->json(), 'id'));
        self::assertSame("Format 501\nInscriptions sur place.", $this->json()['description']);
    }

    public function testACapacitySentAsTextIsRefused(): void
    {
        $this->loginAsSecretary();

        $this->postJson('/api/events', self::event(['capacity' => '12']));
        self::assertResponseStatusCodeSame(422);

        $this->postJson('/api/events', self::event(['capacity' => 12]));
        self::assertResponseStatusCodeSame(201);
    }

    public function testACancellationReasonLongerThanItsColumnIsRefusedNotCut(): void
    {
        $this->loginAsSecretary();
        $this->postJson('/api/events', self::event());
        $id = $this->digString($this->json(), 'id');

        $this->postJson("/api/events/{$id}/cancel", ['reason' => str_repeat('é', 201)]);
        self::assertResponseStatusCodeSame(422);

        $this->postJson("/api/events/{$id}/cancel", ['reason' => ['Pluie']]);
        self::assertResponseStatusCodeSame(422);

        $this->postJson("/api/events/{$id}/cancel", ['reason' => str_repeat('é', 200)]);
        self::assertResponseStatusCodeSame(204);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private static function event(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Tournoi d\'automne',
            'description' => 'Format 501 double out.',
            'location' => 'Bar Le 180, Lyon',
            'startsAt' => '2026-10-24T14:00:00+02:00',
            'endsAt' => '2026-10-24T20:00:00+02:00',
        ];
    }

    private function loginAsSecretary(): void
    {
        $secretary = $this->createUser('secretary@example.com');
        $secretary->grantRole(Role::Secretary);
        $this->entityManager()->flush();
        $this->loginWithTwoFactor('secretary@example.com', $this->enableTwoFactor($secretary));
    }
}
