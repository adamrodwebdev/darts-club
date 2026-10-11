<?php

namespace App\ClubEvent;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class PurgeAudienceHandler
{
    public function __construct(
        private readonly EventAudience $audience,
    ) {
    }

    public function __invoke(PurgeAudience $message): void
    {
        $this->audience->purge();
    }
}
