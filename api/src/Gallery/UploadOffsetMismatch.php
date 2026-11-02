<?php

namespace App\Gallery;

use App\Translation\TranslatableError;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * Morceau qui ne commence pas là où l'envoi s'est arrêté (doublon après une coupure, ou deux
 * envois en même temps) : 409, l'appareil relit la position et reprend de là.
 */
final class UploadOffsetMismatch extends ConflictHttpException implements TranslatableInterface
{
    use TranslatableError;

    public function __construct(int $received)
    {
        parent::__construct($this->remember('L’envoi reprend à %received% octets.', ['%received%' => $received]));
    }
}
