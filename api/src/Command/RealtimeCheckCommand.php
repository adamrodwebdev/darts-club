<?php

namespace App\Command;

use App\Notification\RealtimeCheckFailed;
use App\Notification\RealtimeHubProbe;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:realtime:check', description: 'Vérifie le hub Mercure : abonnement et publication avec les jetons de l’API')]
final class RealtimeCheckCommand
{
    public function __construct(
        private readonly RealtimeHubProbe $probe,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        try {
            $this->probe->check();
        } catch (RealtimeCheckFailed $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success('Hub Mercure : abonnement accepté, publication reçue.');

        return Command::SUCCESS;
    }
}
