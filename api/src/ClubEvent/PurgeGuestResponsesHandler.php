<?php

namespace App\ClubEvent;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class PurgeGuestResponsesHandler
{
    public function __construct(
        private readonly GuestResponses $responses,
    ) {
    }

    public function __invoke(PurgeGuestResponses $message): void
    {
        $this->responses->purge();
    }
}
