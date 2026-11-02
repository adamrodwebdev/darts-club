<?php

namespace App\Command;

use App\Media\MediaCheckFailed;
use App\Media\MediaPipelineProbe;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Exception\ProcessFailedException;

#[AsCommand(name: 'app:media:check', description: 'Vérifie la galerie vidéo : formats des téléphones convertis, décodeur hors liste refusé')]
final class MediaCheckCommand
{
    public function __construct(
        private readonly MediaPipelineProbe $probe,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        try {
            $io->listing($this->probe->check());
        } catch (MediaCheckFailed|ProcessFailedException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success('Galerie vidéo : conversions et refus conformes.');

        return Command::SUCCESS;
    }
}
