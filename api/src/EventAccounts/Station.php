<?php

declare(strict_types=1);

namespace App\EventAccounts;

/** Qui sert un article de la carte : les boissons restent au bar, les plats partent en cuisine. */
enum Station: string
{
    case Bar = 'bar';
    case Kitchen = 'cuisine';
}
