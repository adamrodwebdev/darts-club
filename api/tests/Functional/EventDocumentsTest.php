<?php

namespace App\Tests\Functional;

use App\ClubEvent\EventDetails;
use App\Documents\DocumentType;
use App\Documents\FrenchTypography;
use App\Entity\ClubEvent;
use App\Enum\Role;
use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Démarches des soirées : documents pré-remplis depuis la soirée, la fiche de l'association et le
 * bilan ; le secrétariat les ajuste (seuls ses changements sont gardés) et suit leur avancement.
 */
final class EventDocumentsTest extends ApiTestCase
{
    use BoardScenario;

    private const PROFILE = [
        'officialName' => 'Skull Darts 71',
        'rnaNumber' => 'w712 001 234',
        'siret' => '',
        'seatAddress' => "12 rue des Cibles\n71270 Bellevesvre",
        'presidentName' => 'Camille Martin',
        'secretaryName' => 'Dominique Petit',
        'treasurerName' => '',
        'insurerName' => '',
        'insurancePolicy' => '',
    ];

    protected function tearDown(): void
    {
        foreach ($this->storedDocuments() as $file) {
            unlink($file);
        }
        parent::tearDown();
    }

    public function testTheSecretaryPreparesTheTemporaryBarRequestFromTheEvening(): void
    {
        $event = $this->evening('2026-11-14 20:00');
        $this->board(['secretary@example.com' => Role::Secretary]);
        $this->loginAs('secretary@example.com');
        $this->request('PUT', '/api/association-profile', self::PROFILE);
        self::assertResponseIsSuccessful();
        self::assertSame('W712001234', $this->json()['rnaNumber']);
        self::assertSame(['insurer'], $this->json()['missing']);

        // La liste : une démarche par document, avec sa date conseillée et son avancement
        $this->client->request('GET', $this->proceduresUri($event));
        self::assertResponseIsSuccessful();
        $procedures = array_column((array) $this->json()['procedures'], null, 'type');
        self::assertSame(array_map(static fn (DocumentType $type): string => $type->value, DocumentType::cases()), array_keys($procedures));
        self::assertSame(['status' => 'a-faire', 'recipient' => 'Mairie de Bellevesvre', 'deadline' => '2026-10-30'], array_intersect_key((array) $procedures['buvette'], array_flip(['status', 'recipient', 'deadline'])));
        self::assertSame('pas-necessaire', $this->dig($procedures, 'domaine-public', 'status'));
        self::assertNull($this->dig($procedures, 'budget', 'deadline'));
        self::assertTrue($this->json()['mayEdit']);

        // Le courrier reprend la fiche et la soirée
        $this->client->request('GET', $this->proceduresUri($event).'/buvette');
        self::assertResponseIsSuccessful();
        // Typographie française : ni guillemet, ni deux-points, ni heure coupés en fin de ligne
        $nbsp = FrenchTypography::NBSP;
        self::assertStringContainsString("«{$nbsp}Soirée fléchettes{$nbsp}», le samedi 14 novembre 2026, de 20{$nbsp}h à 0{$nbsp}h", $this->blocks(typography: true)['event']);
        self::assertStringStartsWith("Objet{$nbsp}: ", $this->blocks(typography: true)['subject']);
        $blocks = $this->blocks();
        self::assertSame("Skull Darts 71\nAssociation loi 1901 · RNA W712001234\n12 rue des Cibles\n71270 Bellevesvre", $blocks['sender']);
        self::assertSame("Madame, Monsieur le Maire\nMairie de Bellevesvre", $blocks['recipient']);
        self::assertSame('Bellevesvre, le 15 octobre 2026', $blocks['place-date']);
        self::assertStringContainsString('« Soirée fléchettes », le samedi 14 novembre 2026, de 20 h à 0 h, Salle des fêtes, Bellevesvre.', $blocks['event']);
        self::assertStringContainsString('et du groupe 3', $blocks['request']);
        self::assertSame('Il s’agit de la 1re demande de l’association pour l’année 2026.', $blocks['count']);
        self::assertSame("Camille Martin\nPrésidence de Skull Darts 71", $blocks['signature']);

        // Le secrétariat change un champ et réécrit un passage
        $this->request('PUT', $this->proceduresUri($event).'/buvette', [
            'values' => ['commune' => 'Pierre-de-Bresse', 'group3' => false, 'signer' => 'secretariat', 'unknown' => 'ignoré'],
            'rewrites' => ['politeness' => 'Avec nos remerciements, veuillez agréer nos salutations.', 'count' => ' ', 'nope' => 'ignoré'],
        ]);
        self::assertResponseIsSuccessful();
        self::assertSame('prete', $this->json()['status']);
        $blocks = $this->blocks();
        self::assertSame("Madame, Monsieur le Maire\nMairie de Pierre-de-Bresse", $blocks['recipient']);
        self::assertStringNotContainsString('groupe 3', $blocks['request']);
        self::assertSame("Dominique Petit\nSecrétariat de Skull Darts 71", $blocks['signature']);
        self::assertSame('Avec nos remerciements, veuillez agréer nos salutations.', $blocks['politeness']);
        // Passage vidé : retiré du courrier
        self::assertSame('', $blocks['count']);
        self::assertSame(['count', 'politeness'], array_column(array_filter((array) $this->json()['blocks'], static fn (mixed $block): bool => \is_array($block) && true === $block['rewritten']), 'id'));

        // Les champs non modifiés suivent la soirée : elle est avancée d'une semaine
        $this->reschedule($event, '2026-11-07 20:30');
        $this->client->request('GET', $this->proceduresUri($event).'/buvette');
        self::assertSame('le samedi 7 novembre 2026, de 20 h 30 à 0 h 30', $this->field('when'));
        self::assertSame('Pierre-de-Bresse', $this->field('commune'));
        self::assertSame('2026-10-23', $this->json()['deadline']);

        // Avancement : la date d'envoi est notée
        $this->request('PUT', $this->proceduresUri($event).'/buvette/status', ['status' => 'envoyee']);
        self::assertResponseIsSuccessful();
        self::assertSame(['envoyee', '2026-10-15', null], [$this->json()['status'], $this->json()['sentOn'], $this->json()['receivedOn']]);
        $this->request('PUT', $this->proceduresUri($event).'/buvette/status', ['status' => 'recue']);
        self::assertSame(['recue', '2026-10-15', '2026-10-15'], [$this->json()['status'], $this->json()['sentOn'], $this->json()['receivedOn']]);
        $this->request('PUT', $this->proceduresUri($event).'/buvette/status', ['status' => 'inconnu']);
        self::assertResponseStatusCodeSame(422);

        $trail = $this->auditTrail();
        self::assertSame(['association.update', 'procedure.save', 'procedure.status', 'procedure.status'], array_column($trail, 'action'));
        self::assertSame(['document' => 'buvette', 'status' => 'recue'], $trail[3]['details']);
    }

    public function testEveryDocumentRendersForABareEvening(): void
    {
        $event = $this->evening('2026-11-14 20:00');
        $this->board(['secretary@example.com' => Role::Secretary]);
        $this->loginAs('secretary@example.com');

        foreach (DocumentType::cases() as $type) {
            $this->client->request('GET', $this->proceduresUri($event).'/'.$type->value);
            self::assertResponseIsSuccessful($type->value);
            self::assertNotEmpty($this->json()['blocks'], $type->value);
        }
        // Fiche vide : ce qui manque apparaît entre crochets
        $this->client->request('GET', $this->proceduresUri($event).'/salle');
        self::assertStringContainsString('[numéro RNA]', $this->blocks()['sender']);
        self::assertSame(['rna', 'seat', 'president', 'insurer'], $this->json()['missing']);
        $this->client->request('GET', $this->proceduresUri($event).'/inconnu');
        self::assertResponseStatusCodeSame(404);
    }

    public function testThePreviewShowsChangesWithoutSavingThem(): void
    {
        $event = $this->evening('2026-11-14 20:00');
        $this->board(['secretary@example.com' => Role::Secretary]);
        $this->loginAs('secretary@example.com');

        $this->postJson($this->proceduresUri($event).'/fermeture-tardive/preview', ['values' => ['until' => '4 h']]);
        self::assertResponseIsSuccessful();
        self::assertStringEndsWith('jusqu’à 4 h.', $this->blocks()['request']);

        $this->client->request('GET', $this->proceduresUri($event).'/fermeture-tardive');
        self::assertStringEndsWith('jusqu’à 3 h.', $this->blocks()['request']);
        self::assertSame(['pas-necessaire', false], [$this->json()['status'], $this->json()['edited']]);
        self::assertSame([], $this->auditTrail());
    }

    public function testTheBudgetAndTheSacemSheetFollowTheEveningAccounts(): void
    {
        $event = $this->evening('2026-11-14 20:00');
        $this->board(['secretary@example.com' => Role::Secretary]);
        $this->loginAs('secretary@example.com');
        $accounts = "/api/events/{$event->getId()->toRfc4122()}/accounts";
        $this->postJson($accounts.'/items', ['label' => 'Bière', 'planned' => 10, 'bought' => 12, 'returned' => 2, 'sold' => 8, 'purchasePrice' => '1', 'salePrice' => '3']);
        $this->postJson($accounts.'/entries', ['kind' => 'depense', 'label' => 'Location de la salle', 'planned' => '80']);
        $this->postJson($accounts.'/entries', ['kind' => 'recette', 'label' => 'Dons', 'planned' => '0', 'actual' => '15']);
        $rent = $this->digString($this->json(), 'entries', 0, 'id');

        $this->client->request('GET', $this->proceduresUri($event).'/budget');
        $items = array_column(array_filter((array) $this->json()['blocks'], static fn (mixed $block): bool => \is_array($block) && 'ligne' === $block['kind']), 'text', 'label');
        $euro = "\u{00A0}€";
        self::assertSame("prévu 10,00{$euro} · réel 10,00{$euro}", $items['Achats buvette · Bière']);
        self::assertSame("prévu 80,00{$euro} · réel à venir", $items['Location de la salle']);
        self::assertSame("prévu 30,00{$euro} · réel 24,00{$euro}", $items['Ventes buvette · Bière']);
        self::assertSame("prévu 0,00{$euro} · réel 15,00{$euro}", $items['Dons']);
        self::assertSame("prévu −60,00{$euro} · réel +29,00{$euro}", $items['Résultat']);

        $this->client->request('GET', $this->proceduresUri($event).'/sacem');
        self::assertSame("90,00{$euro}", $this->blocks(typography: true)['budget']);

        // Réécriture d'une ligne du bilan : oubliée quand la ligne disparaît
        $this->request('PUT', $this->proceduresUri($event).'/budget', ['rewrites' => ["entry-{$rent}" => 'prévu 80,00 € (salle prêtée par la mairie)']]);
        self::assertSame(true, $this->json()['edited']);
        $this->request('DELETE', "/api/event-accounts/entries/{$rent}");
        $this->request('PUT', $this->proceduresUri($event).'/budget', ['rewrites' => ["entry-{$rent}" => 'prévu 80,00 € (salle prêtée par la mairie)']]);
        self::assertSame(false, $this->json()['edited']);
    }

    public function testTicketsAreNumberedWithinLimits(): void
    {
        $event = $this->evening('2026-11-14 20:00');
        $this->board(['secretary@example.com' => Role::Secretary]);
        $this->loginAs('secretary@example.com');

        $this->request('PUT', $this->proceduresUri($event).'/billets', ['values' => ['count' => '9999', 'first' => '101', 'price' => '', 'category' => 'Entrée']]);
        self::assertResponseIsSuccessful();
        self::assertSame(['organizer' => 'Skull Darts 71', 'event' => 'Soirée fléchettes · samedi 14 novembre 2026', 'category' => 'Entrée', 'price' => 'Gratuit', 'first' => 101, 'count' => 500], $this->json()['tickets']);
        self::assertSame('n° 101 à 600 (500 billets) · Entrée · Gratuit', $this->blocks()['series']);
    }

    public function testTheTreasurerReadsThePresidencyEditsAndMembersSeeNothing(): void
    {
        $event = $this->evening('2026-11-14 20:00');
        $this->board(['treasurer@example.com' => Role::Treasurer, 'president@example.com' => Role::President]);
        $this->createUser('alice@example.com');

        $this->loginAs('treasurer@example.com');
        $this->client->request('GET', $this->proceduresUri($event));
        self::assertResponseIsSuccessful();
        self::assertFalse($this->json()['mayEdit']);
        $this->request('PUT', $this->proceduresUri($event).'/salle', ['values' => ['venue' => 'Gymnase']]);
        self::assertResponseStatusCodeSame(403);
        $this->request('PUT', '/api/association-profile', self::PROFILE);
        self::assertResponseStatusCodeSame(403);

        $this->loginAs('president@example.com');
        $this->request('PUT', $this->proceduresUri($event).'/salle', ['values' => ['venue' => 'Gymnase']]);
        self::assertResponseIsSuccessful();

        $this->client->getCookieJar()->clear();
        $this->login('alice@example.com', self::PASSWORD);
        $this->client->request('GET', $this->proceduresUri($event));
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/api/association-profile');
        self::assertResponseStatusCodeSame(403);
    }

    public function testTheProfileRefusesMalformedNumbersInTheMemberLanguage(): void
    {
        $this->board(['secretary@example.com' => Role::Secretary]);
        $this->loginAs('secretary@example.com');

        $this->request('PUT', '/api/association-profile', ['rnaNumber' => 'X123'] + self::PROFILE);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('Numéro RNA invalide : « W » suivi de 9 chiffres.', $this->json()['error'] ?? $this->json()['detail'] ?? null);

        $this->client->request('PUT', '/api/association-profile', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => self::FRONT_ORIGIN, 'HTTP_ACCEPT_LANGUAGE' => 'en'], content: json_encode(['siret' => '123'] + self::PROFILE, \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(422);
        self::assertSame('Invalid SIRET number: 14 digits.', $this->json()['error'] ?? $this->json()['detail'] ?? null);
    }

    public function testTheInsuranceCertificateIsAPrivatePdf(): void
    {
        $event = $this->evening('2026-11-14 20:00');
        $this->board(['secretary@example.com' => Role::Secretary]);
        $this->createUser('alice@example.com');
        $this->loginAs('secretary@example.com');

        $this->uploadCertificate("%PDF-1.4\n%ancienne\n", '2026-08-31');
        self::assertResponseIsSuccessful();
        self::assertSame(['validUntil' => '2026-08-31', 'valid' => false], $this->json()['certificate']);
        $this->uploadCertificate("%PDF-1.4\n%nouvelle\n", '2027-08-31');
        self::assertSame(['validUntil' => '2027-08-31', 'valid' => true], $this->json()['certificate']);
        self::assertCount(1, $this->storedDocuments());

        $this->client->request('GET', '/api/association-profile/insurance');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        self::assertStringStartsWith('attachment;', (string) $this->client->getResponse()->headers->get('Content-Disposition'));

        $this->client->request('GET', $this->proceduresUri($event).'/assurance');
        self::assertSame('L’attestation annuelle rangée dans la fiche (valable jusqu’au 31 août 2027) couvre déjà cette date : elle peut suffire.', $this->blocks()['certificate']);

        // Ni un autre fichier, ni une date impossible, ni un adhérent
        $this->uploadCertificate('pas un PDF', '2027-08-31');
        self::assertResponseStatusCodeSame(422);
        self::assertSame('L’attestation doit être un PDF.', $this->json()['error'] ?? $this->json()['detail'] ?? null);
        $this->uploadCertificate("%PDF-1.4\n", '2027-02-30');
        self::assertResponseStatusCodeSame(422);
        self::assertCount(1, $this->storedDocuments());

        $this->client->getCookieJar()->clear();
        $this->login('alice@example.com', self::PASSWORD);
        $this->client->request('GET', '/api/association-profile/insurance');
        self::assertResponseStatusCodeSame(403);
    }

    private function proceduresUri(ClubEvent $event): string
    {
        return "/api/events/{$event->getId()->toRfc4122()}/procedures";
    }

    /**
     * Textes du document, par identifiant de passage, espaces insécables lus comme des espaces
     * (lisibilité des attentes ; la typographie a son propre test).
     *
     * @return array<string, string>
     */
    private function blocks(bool $typography = false): array
    {
        $texts = [];
        foreach ((array) $this->json()['blocks'] as $block) {
            $text = $this->digString($block, 'text');
            $texts[$this->digString($block, 'id')] = $typography ? $text : str_replace(FrenchTypography::NBSP, ' ', $text);
        }

        return $texts;
    }

    /** Valeur d'un champ du document, espaces insécables lus comme des espaces */
    private function field(string $key): string
    {
        foreach ((array) $this->json()['fields'] as $index => $field) {
            if ($key === $this->digString($field, 'key')) {
                return str_replace(FrenchTypography::NBSP, ' ', $this->digString($this->json(), 'fields', $index, 'value'));
            }
        }
        self::fail("Champ « {$key} » absent.");
    }

    private function reschedule(ClubEvent $event, string $startsAt): void
    {
        $start = new \DateTimeImmutable($startsAt.' Europe/Paris');
        $event = $this->entityManager()->find(ClubEvent::class, $event->getId());
        self::assertInstanceOf(ClubEvent::class, $event);
        $event->edit(new EventDetails('Soirée fléchettes', 'Initiation', 'Salle des fêtes, Bellevesvre', $start, $start->modify('+4 hours')), $this->now());
        $event->releaseEvents();
        $this->entityManager()->flush();
    }

    private function uploadCertificate(string $content, string $validUntil): void
    {
        $path = tempnam(sys_get_temp_dir(), 'insurance');
        self::assertIsString($path);
        file_put_contents($path, $content);
        $this->client->request('POST', '/api/association-profile/insurance', ['validUntil' => $validUntil], ['file' => new UploadedFile($path, 'attestation.pdf', test: true)], ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);
    }

    /** @return list<string> */
    private function storedDocuments(): array
    {
        $root = self::getContainer()->getParameter('kernel.project_dir');
        self::assertIsString($root);
        $files = glob($root.'/var/storage/documents/*');

        return false === $files ? [] : $files;
    }
}
