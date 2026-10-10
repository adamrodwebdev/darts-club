<?php

namespace App\Command;

use App\Enum\Role;
use App\Repository\UserRepository;
use App\ValueObject\EmailAddress;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Amorçage de la production : nomme le premier président (ou un administrateur)
 * sur un compte déjà inscrit et confirmé. Ensuite, la présidence gère les rôles
 * depuis l'interface.
 */
#[AsCommand(name: 'app:member:grant-role', description: 'Attribue un rôle du bureau à un compte confirmé (premier président, administrateur)')]
final class GrantBoardRoleCommand
{
    private const ROLES = [
        'president' => Role::President,
        'tresorier' => Role::Treasurer,
        'secretaire' => Role::Secretary,
        'admin' => Role::Admin,
    ];

    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Email du compte (inscrit et confirmé)')] string $email,
        #[Argument('president, tresorier, secretaire ou admin')] string $role,
    ): int {
        $granted = self::ROLES[$role] ?? null;
        if (null === $granted) {
            $io->error('Rôle inconnu. Valeurs possibles : '.implode(', ', array_keys(self::ROLES)).'.');

            return Command::INVALID;
        }

        $user = $this->users->findOneByEmail(EmailAddress::fromString($email));
        if (null === $user || !$user->isVerified()) {
            $io->error('Aucun compte confirmé avec cet email : inscris-toi sur le site et clique sur le lien reçu, puis relance la commande.');

            return Command::FAILURE;
        }

        $user->grantRole($granted);
        $this->em->flush();
        $this->logger->notice('Rôle {role} attribué en ligne de commande à {user}', ['role' => $granted->value, 'user' => $user->getId()->toRfc4122()]);

        $io->success(\sprintf('%s a maintenant le rôle %s. À la prochaine connexion, la double authentification sera demandée.', $email, $role));

        return Command::SUCCESS;
    }
}
