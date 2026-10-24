<?php

namespace App\Documents;

/** Morceaux d'un document, mis en page par l'écran (courrier ou fiche). */
enum BlockKind: string
{
    case Sender = 'expediteur';
    case Recipient = 'destinataire';
    case PlaceDate = 'lieu-date';
    case Subject = 'objet';
    case Paragraph = 'paragraphe';
    case Signature = 'signature';
    case Heading = 'titre';
    /** Ligne « intitulé : valeur » d'une fiche */
    case Item = 'ligne';
    /** Rappel pour le secrétariat (n'est pas un engagement de l'association) */
    case Note = 'note';
}
