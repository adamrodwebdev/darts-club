<?php

namespace App\Enum;

/** Rôles de l'association ; la hiérarchie est définie dans security.yaml. */
enum Role: string
{
    case Member = 'ROLE_MEMBER';
    case Secretary = 'ROLE_SECRETARY';
    case Treasurer = 'ROLE_TREASURER';
    case President = 'ROLE_PRESIDENT';
    case Admin = 'ROLE_ADMIN';
}
