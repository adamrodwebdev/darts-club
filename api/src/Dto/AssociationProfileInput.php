<?php

namespace App\Dto;

use App\Documents\AssociationDetails;
use Symfony\Component\Validator\Constraints as Assert;

/** Fiche de l'association : champ vide = inconnu (il apparaîtra entre crochets dans les courriers). */
final class AssociationProfileInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: AssociationDetails::NAME_MAX)]
        public readonly string $officialName = '',

        #[Assert\Length(max: 20)]
        public readonly ?string $rnaNumber = null,

        #[Assert\Length(max: 20)]
        public readonly ?string $siret = null,

        #[Assert\Length(max: AssociationDetails::ADDRESS_MAX)]
        public readonly ?string $seatAddress = null,

        #[Assert\Length(max: AssociationDetails::PERSON_MAX)]
        public readonly ?string $presidentName = null,

        #[Assert\Length(max: AssociationDetails::PERSON_MAX)]
        public readonly ?string $secretaryName = null,

        #[Assert\Length(max: AssociationDetails::PERSON_MAX)]
        public readonly ?string $treasurerName = null,

        #[Assert\Length(max: AssociationDetails::PERSON_MAX)]
        public readonly ?string $insurerName = null,

        #[Assert\Length(max: AssociationDetails::POLICY_MAX)]
        public readonly ?string $insurancePolicy = null,
    ) {
    }

    public function toDetails(): AssociationDetails
    {
        return new AssociationDetails(
            $this->officialName,
            rnaNumber: self::compact($this->rnaNumber, uppercase: true),
            siret: self::compact($this->siret),
            seatAddress: self::text($this->seatAddress),
            presidentName: self::text($this->presidentName),
            secretaryName: self::text($this->secretaryName),
            treasurerName: self::text($this->treasurerName),
            insurerName: self::text($this->insurerName),
            insurancePolicy: self::text($this->insurancePolicy),
        );
    }

    private static function text(?string $value): ?string
    {
        $value = trim((string) $value);

        return '' === $value ? null : $value;
    }

    /** Numéro saisi avec ou sans espaces (« W712 001 234 », « 123 456 789 00012 ») */
    private static function compact(?string $value, bool $uppercase = false): ?string
    {
        $value = preg_replace('/\s+/u', '', (string) $value) ?? '';

        return '' === $value ? null : ($uppercase ? strtoupper($value) : $value);
    }
}
