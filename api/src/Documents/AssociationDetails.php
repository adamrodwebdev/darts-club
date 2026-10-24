<?php

namespace App\Documents;

use App\Translation\InvalidValue;

/**
 * Fiche de l'association reprise dans les documents (privée, jamais publiée sur le site) : nom officiel,
 * numéros RNA et SIRET, siège, noms des membres du bureau (les pseudos du site ne conviennent pas à un
 * courrier officiel), assureur.
 */
final readonly class AssociationDetails
{
    public const NAME_MAX = 120;
    public const PERSON_MAX = 80;
    public const ADDRESS_MAX = 300;
    public const POLICY_MAX = 60;

    public string $officialName;

    public function __construct(
        string $officialName,
        /** Répertoire national des associations : « W » et 9 chiffres */
        public ?string $rnaNumber = null,
        public ?string $siret = null,
        public ?string $seatAddress = null,
        public ?string $presidentName = null,
        public ?string $secretaryName = null,
        public ?string $treasurerName = null,
        public ?string $insurerName = null,
        public ?string $insurancePolicy = null,
    ) {
        $officialName = trim($officialName);
        if ('' === $officialName || mb_strlen($officialName) > self::NAME_MAX) {
            throw new InvalidValue('Le nom doit faire entre 1 et %max% caractères.', ['%max%' => self::NAME_MAX]);
        }
        $this->officialName = $officialName;
        if (null !== $rnaNumber && 1 !== preg_match('/^W\d{9}$/', $rnaNumber)) {
            throw new InvalidValue('Numéro RNA invalide : « W » suivi de 9 chiffres.');
        }
        if (null !== $siret && 1 !== preg_match('/^\d{14}$/', $siret)) {
            throw new InvalidValue('Numéro SIRET invalide : 14 chiffres.');
        }
    }

    /**
     * Ce qui manque pour des courriers complets (codes traduits par l'écran).
     *
     * @return list<string>
     */
    public function missing(): array
    {
        return array_keys(array_filter([
            'rna' => null === $this->rnaNumber,
            'seat' => null === $this->seatAddress,
            'president' => null === $this->presidentName,
            'insurer' => null === $this->insurerName,
        ]));
    }
}
