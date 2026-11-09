<?php

declare(strict_types=1);

namespace App\Media;

use App\Enum\VideoFailure;

/** Vidéo refusée à la conversion : trop longue une fois mesurée, ou illisible. */
final class VideoRejected extends \RuntimeException
{
    public function __construct(
        public readonly VideoFailure $failure,
        ?\Throwable $previous = null,
    ) {
        parent::__construct('Vidéo refusée : '.$failure->value, 0, $previous);
    }
}
