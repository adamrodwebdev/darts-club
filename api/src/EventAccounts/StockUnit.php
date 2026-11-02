<?php

namespace App\EventAccounts;

/**
 * Comment un article se compte en stock : au litre (en centilitres, fût de bière, cubi de vin), à
 * l'unité (bouteille d'eau, canette) ou à la portion (hot dog, frites).
 */
enum StockUnit: string
{
    case Litre = 'litre';
    case Unit = 'unite';
    case Portion = 'portion';
}
