<?php

namespace App\Dev;

use App\Announcement\AnnouncementText;
use App\ClubEvent\ClubTime;
use App\ClubEvent\EventDetails;
use App\Entity\Announcement;
use App\Entity\ClubEvent;
use App\Entity\Conversation;
use App\Entity\EventPost;
use App\Entity\Membership;
use App\Entity\Notification;
use App\Entity\User;
use App\Enum\Role;
use App\Event\RecordsDomainEvents;
use App\Membership\Money;
use App\Membership\PaymentDetails;
use App\Membership\PaymentMethod;
use App\Membership\Season;
use App\Membership\SeasonFee;
use App\Messaging\Mailbox;
use App\Messaging\MailboxTopic;
use App\Messaging\MessageBody;
use App\Notification\Category;
use App\Notification\NotificationContent;
use App\ValueObject\DisplayName;
use App\ValueObject\EmailAddress;
use App\ValueObject\NewAccount;
use App\ValueObject\PhoneNumber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

/**
 * Développement uniquement : recrée une base SQLite de démonstration
 * (comptes, cotisations, événements, archives, messages, annonces).
 */
#[When(env: 'dev')]
#[AsCommand(name: 'app:demo:seed', description: 'Recrée la base de démonstration locale (SQLite, dev uniquement)')]
final class DemoSeedCommand
{
    public const PASSWORD = 'SkullDarts71-demo!';

    /** @var list<object> */
    private array $entities = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PasswordHasherFactoryInterface $hashers,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        if (!str_contains($this->em->getConnection()->getDatabasePlatform()::class, 'SQLite')) {
            $io->error('Commande réservée à la base SQLite de démonstration.');

            return Command::FAILURE;
        }

        $schema = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);

        // Heures de démo exprimées en heure de Paris
        $now = $this->clock->now()->setTimezone(new \DateTimeZone(ClubTime::TIMEZONE));
        $season = Season::containing($now);

        $president = $this->member('president@demo.local', 'Patrick', Role::President);
        $treasurer = $this->member('tresorier@demo.local', 'Thierry', Role::Treasurer);
        $secretary = $this->member('secretaire@demo.local', 'Sandrine', Role::Secretary);
        $alice = $this->member('alice@demo.local', 'Alice', null, '06 12 34 56 78');
        $bob = $this->member('bob@demo.local', 'Bob la Fléchette');
        $chloe = $this->member('chloe@demo.local', 'Chloé');

        // Cotisations de la saison : payée, partielle, impayée
        foreach ([$president, $treasurer, $secretary, $alice, $bob, $chloe] as $user) {
            $membership = Membership::open($user, new SeasonFee($season, Money::cents(3000)), $now);
            $this->keep($membership);
            if (\in_array($user, [$president, $treasurer, $secretary, $alice], true)) {
                $membership->recordPayment(new PaymentDetails(Money::cents(3000), PaymentMethod::Transfer, $season->startsAt()->modify('+5 days'), 'Virement septembre'), $treasurer, $now);
            } elseif ($user === $bob) {
                $membership->recordPayment(new PaymentDetails(Money::cents(1500), PaymentMethod::Cash, $season->startsAt()->modify('+12 days')), $treasurer, $now);
            }
        }

        // Événements à venir (deux ouverts au public : carrousel de l'accueil)
        $fridayAt = $now->modify('next friday 20:00');
        $friday = $this->event($secretary, new EventDetails(
            title: 'Soirée fléchettes du vendredi',
            description: 'Parties libres, initiation pour les nouveaux. Ambiance stoner garantie.',
            location: 'Bar du club',
            startsAt: $fridayAt,
            endsAt: $fridayAt->modify('+4 hours'),
        ), $now);
        $friday->register($alice, $now);
        $friday->register($bob, $now);
        $halloweenAt = $now->modify('+8 days 17:00');
        $this->event($secretary, new EventDetails(
            title: 'Halloween Party',
            description: 'Costumes bienvenus, cible spéciale citrouille et tournoi des monstres.',
            location: 'Salle des fêtes',
            startsAt: $halloweenAt,
            endsAt: $halloweenAt->modify('+6 hours'),
            public: true,
            city: 'Bellevesvre',
        ), $now);
        $initiationAt = $now->modify('+12 days 14:00');
        $this->event($secretary, new EventDetails(
            title: 'Initiation découverte',
            description: 'Viens avec un ami : on prête les fléchettes.',
            location: 'Salle municipale',
            startsAt: $initiationAt,
            endsAt: $initiationAt->modify('+3 hours'),
            capacity: 12,
            public: true,
            city: 'Bellevesvre',
        ), $now);

        // Archives avec résumé et souvenirs (deux ouvertes au public)
        $pastAt = $now->modify('-20 days 19:30');
        $past = $this->event($secretary, new EventDetails(
            title: 'Soirée de rentrée',
            description: 'Reprise de la saison.',
            location: 'Bar du club',
            startsAt: $pastAt,
            endsAt: $pastAt->modify('+4 hours'),
            public: true,
            city: 'Bellevesvre',
        ), $now->modify('-2 months'));
        $summerAt = $now->modify('-3 months 15:00');
        $this->event($secretary, new EventDetails(
            title: 'Tournoi d’été',
            description: 'Tournoi en plein air, doublettes tirées au sort.',
            location: 'Parc municipal',
            startsAt: $summerAt,
            endsAt: $summerAt->modify('+5 hours'),
            public: true,
            city: 'Bellevesvre',
        ), $now->modify('-4 months'));
        $past->writeSummary("Belle soirée de reprise : 14 joueurs, deux nouveaux venus et un 180 de Bob au troisième tour.\nMerci à tous !");
        $this->keep(new EventPost(event: $past, author: $bob, body: MessageBody::fromString('Mon premier 180 au club, je ne m’en remets pas !'), now: $now));
        $this->keep(new EventPost(event: $past, author: $alice, body: MessageBody::fromString('Super ambiance, vivement la prochaine.'), now: $now));

        // Canal d'informations
        $pinned = Announcement::publish($president, AnnouncementText::of('Bienvenue sur le nouveau site !', "Calendrier, messagerie, cotisations : tout le club au même endroit.\nN’hésitez pas à compléter votre profil."), $now);
        $pinned->pin();
        $this->keep($pinned);
        $this->keep(Announcement::publish($president, AnnouncementText::of('Nouveaux horaires', 'Les soirées commencent désormais à 20 h.'), $now));

        // Messagerie : entre adhérents et vers la trésorerie
        $direct = Conversation::between($alice, $bob, $now);
        $this->keep($direct);
        $this->keep($direct->post($bob, MessageBody::fromString('Salut Alice, tu viens vendredi ?'), $now));
        $this->keep($direct->post($alice, MessageBody::fromString('Oui ! Je ramène mes nouvelles fléchettes.'), $now));
        $toTreasury = Conversation::toMailbox($bob, new MailboxTopic(Mailbox::Treasurer, 'Cotisation'), $now);
        $this->keep($toTreasury);
        $this->keep($toTreasury->post($bob, MessageBody::fromString('Je règle le reste de ma cotisation vendredi, en espèces.'), $now));

        // Centre de notifications d'Alice
        $this->keep(new Notification($alice, new NotificationContent(Category::Event, 'Nouvel événement : Soirée fléchettes du vendredi', 'Bar du club', '/evenements/'.$friday->getId()->toRfc4122()), $now));
        $this->keep(new Notification($alice, new NotificationContent(Category::Announcement, 'Bienvenue sur le nouveau site !', 'Tout le club au même endroit.', '/infos'), $now));

        // Données de démo : aucun email ni notification à l'enregistrement
        foreach ($this->entities as $entity) {
            if ($entity instanceof RecordsDomainEvents) {
                $entity->releaseEvents();
            }
            $this->em->persist($entity);
        }
        $this->em->flush();

        $io->success('Base de démonstration prête.');
        $io->table(['Compte', 'Rôle'], [
            ['president@demo.local', 'Président (2FA à activer au 1er accès)'],
            ['tresorier@demo.local', 'Trésorier (2FA à activer)'],
            ['secretaire@demo.local', 'Secrétaire (2FA à activer)'],
            ['alice@demo.local', 'Adhérente, cotisation payée'],
            ['bob@demo.local', 'Adhérent, cotisation partielle'],
            ['chloe@demo.local', 'Adhérente, cotisation impayée'],
        ]);
        $io->writeln('Mot de passe commun : <info>'.self::PASSWORD.'</info>');

        return Command::SUCCESS;
    }

    private function member(string $email, string $name, ?Role $role = null, ?string $phone = null): User
    {
        $user = User::register(
            new NewAccount(EmailAddress::fromString($email), null !== $phone ? PhoneNumber::fromInput($phone) : null),
            $this->hashers->getPasswordHasher(User::class)->hash(self::PASSWORD),
            $this->clock->now(),
        );
        $user->markVerified();
        $user->rename(DisplayName::fromString($name));
        if (null !== $role) {
            $user->grantRole($role);
        }
        $this->keep($user);

        return $user;
    }

    private function event(User $organizer, EventDetails $details, \DateTimeImmutable $createdAt): ClubEvent
    {
        $event = ClubEvent::schedule($organizer, $details, $createdAt);
        // Suffixe fixe (démo) : la page publique garde la même adresse d'un réensemencement à l'autre
        $event->assignPublicAddress(substr(hash('sha256', $details->title), 0, 6));
        $this->keep($event);

        return $event;
    }

    private function keep(object $entity): void
    {
        $this->entities[] = $entity;
    }
}
