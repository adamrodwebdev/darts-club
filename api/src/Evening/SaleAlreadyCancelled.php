<?php

declare(strict_types=1);

namespace App\Evening;

/** Deuxième annulation d'une même vente (autre appareil, double appui) : l'API répond 409. */
final class SaleAlreadyCancelled extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Vente déjà annulée.');
    }
}
