<?php

declare(strict_types=1);

namespace App\Dto;

use App\Text\Secret;
use Symfony\Component\Validator\Constraints as Assert;

final class AccountDeletion
{
    public const CONFIRMATION = 'SUPPRIMER';

    public function __construct(
        #[Assert\EqualTo(self::CONFIRMATION, message: 'Tape SUPPRIMER pour confirmer.')]
        public readonly string $confirmation = '',

        /** Obligatoire si le compte a un mot de passe (sinon compte OAuth) */
        #[\SensitiveParameter]
        #[Assert\Length(max: 4096)]
        #[Secret]
        public readonly string $password = '',
    ) {
    }
}
