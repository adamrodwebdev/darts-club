<?php

namespace App\Preference;

/** Thème d'affichage choisi par l'adhérent ; « system » suit le réglage de l'appareil. */
enum Theme: string
{
    case System = 'system';
    case Light = 'light';
    case Dark = 'dark';
}
