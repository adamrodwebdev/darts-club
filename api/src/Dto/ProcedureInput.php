<?php

namespace App\Dto;

use App\Documents\ProcedureContent;
use Symfony\Component\Validator\Constraints as Assert;

/** Document d'une démarche tel que le secrétariat l'a changé : champs, puis passages réécrits. */
final class ProcedureInput
{
    /**
     * @param array<mixed> $values   valeurs des champs, par clé
     * @param array<mixed> $rewrites textes réécrits, par identifiant de passage
     */
    public function __construct(
        #[Assert\Count(max: 40)]
        public readonly array $values = [],

        #[Assert\Count(max: ProcedureContent::REWRITES_MAX)]
        public readonly array $rewrites = [],
    ) {
    }

    public function toContent(): ProcedureContent
    {
        return ProcedureContent::fromInput($this->values, $this->rewrites);
    }
}
