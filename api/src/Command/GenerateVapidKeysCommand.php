<?php

namespace App\Command;

use Minishlink\WebPush\VAPID;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:push:generate-vapid-keys', description: 'Génère une paire de clés VAPID pour le Web Push')]
final class GenerateVapidKeysCommand
{
    public function __invoke(SymfonyStyle $io): int
    {
        ['publicKey' => $public, 'privateKey' => $private] = VAPID::createVapidKeys();
        if (!\is_string($public) || !\is_string($private)) {
            throw new \RuntimeException('Génération des clés VAPID impossible.');
        }

        $io->writeln('VAPID_PUBLIC_KEY='.$public);
        $io->note('Clé privée à stocker hors du dépôt : bin/console secrets:set VAPID_PRIVATE_KEY');
        $io->writeln('VAPID_PRIVATE_KEY='.$private);

        return Command::SUCCESS;
    }
}
