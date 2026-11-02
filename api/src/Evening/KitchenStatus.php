<?php

namespace App\Evening;

/**
 * Où en est la part cuisine d'une commande. Ordre imposé par le serveur : en attente → prête →
 * remise, ou annulée tant qu'elle attend. Une commande de boissons seules n'a pas de statut.
 */
enum KitchenStatus: string
{
    case Waiting = 'en_attente';
    case Ready = 'prete';
    case Delivered = 'remise';
    case Cancelled = 'annulee';
}
