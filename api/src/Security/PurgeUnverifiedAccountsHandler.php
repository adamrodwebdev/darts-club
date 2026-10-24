<?php

namespace App\Security;

use App\Repository\UserRepository;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Comptes jamais confirmés depuis 7 jours : supprimés. Ni adresses inscrites par des tiers gardées
 * sans fin, ni données conservées sans raison (RGPD) ; le titulaire peut se réinscrire.
 */
#[AsMessageHandler]
final class PurgeUnverifiedAccountsHandler
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(PurgeUnverifiedAccounts $message): void
    {
        $this->users->deleteUnverifiedCreatedBefore($this->clock->now()->modify('-7 days'));
    }
}
