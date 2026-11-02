<?php

namespace App\Evening;

/** Équipes d'une soirée, choisies par le bureau : chacune ouvrira son écran (accueil, bar, cuisine). */
enum Team: string
{
    case Welcome = 'accueil';
    case Bar = 'bar';
    case Kitchen = 'cuisine';
}
