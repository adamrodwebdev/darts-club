<?php

namespace App\Documents;

/** Démarches d'une soirée, préparées par le secrétariat (identifiants des adresses du site). */
enum DocumentType: string
{
    case TemporaryBar = 'buvette';
    case Venue = 'salle';
    case PublicSpace = 'domaine-public';
    case Sacem = 'sacem';
    case Budget = 'budget';
    case Tickets = 'billets';
    case Insurance = 'assurance';
    case LateClosing = 'fermeture-tardive';
}
