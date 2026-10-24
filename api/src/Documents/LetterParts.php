<?php

namespace App\Documents;

/**
 * Morceaux communs aux courriers : en-tête de l'association, destinataire, lieu et date, objet,
 * formules et signature. Ce qui manque à la fiche apparaît entre crochets, à compléter.
 */
final class LetterParts
{
    /** Commune du siège, la seule adresse publique de l'association */
    public const SEAT_TOWN = 'Bellevesvre';

    public const SIGNERS = ['presidence', 'secretariat'];

    public static function sender(AssociationDetails $details): DocumentBlock
    {
        $identity = 'Association loi 1901'.(null === $details->rnaNumber ? ' · RNA [numéro RNA]' : ' · RNA '.$details->rnaNumber);

        return new DocumentBlock('sender', BlockKind::Sender, implode("\n", [
            $details->officialName,
            $identity,
            $details->seatAddress ?? '[adresse du siège]',
        ]));
    }

    public static function recipient(string $text): DocumentBlock
    {
        return new DocumentBlock('recipient', BlockKind::Recipient, $text);
    }

    public static function placeAndDate(\DateTimeImmutable $today): DocumentBlock
    {
        return new DocumentBlock('place-date', BlockKind::PlaceDate, self::SEAT_TOWN.', le '.FrenchDates::date($today));
    }

    public static function subject(string $text): DocumentBlock
    {
        return new DocumentBlock('subject', BlockKind::Subject, 'Objet : '.$text);
    }

    public static function paragraph(string $id, string $text): DocumentBlock
    {
        return new DocumentBlock($id, BlockKind::Paragraph, $text);
    }

    public static function note(string $id, string $text): DocumentBlock
    {
        return new DocumentBlock($id, BlockKind::Note, $text);
    }

    public static function politeness(string $addressee): DocumentBlock
    {
        return self::paragraph('politeness', 'Nous vous prions d’agréer, '.$addressee.', l’expression de nos salutations distinguées.');
    }

    /** @param string $signer « presidence » ou « secretariat » */
    public static function signature(AssociationDetails $details, string $signer): DocumentBlock
    {
        [$name, $office] = 'secretariat' === $signer
            ? [$details->secretaryName ?? '[nom du secrétaire]', 'Secrétariat']
            : [$details->presidentName ?? '[nom de la présidence]', 'Présidence'];

        return new DocumentBlock('signature', BlockKind::Signature, $name."\n".$office.' de '.$details->officialName);
    }

    /** Champ « signé par », proposé dans chaque courrier */
    public static function signerField(): FieldDefinition
    {
        return FieldDefinition::choice('signer', self::SIGNERS, 'presidence');
    }
}
