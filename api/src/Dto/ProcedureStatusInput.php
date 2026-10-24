<?php

namespace App\Dto;

use App\Documents\ProcedureStatus;
use Symfony\Component\Validator\Constraints as Assert;

/** Avancement d'une démarche : à faire, prête, envoyée, reçue, pas pour cette soirée. */
final class ProcedureStatusInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(callback: [self::class, 'statuses'])]
        public readonly string $status = '',
    ) {
    }

    /** @return list<string> */
    public static function statuses(): array
    {
        return array_map(static fn (ProcedureStatus $status): string => $status->value, ProcedureStatus::cases());
    }

    public function toStatus(): ProcedureStatus
    {
        return ProcedureStatus::from($this->status);
    }
}
