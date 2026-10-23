<?php

namespace App\Messaging;

/** Boîte du bureau visée et sujet facultatif d'un nouveau fil */
final readonly class MailboxTopic
{
    public const SUBJECT_MAX = 120;

    public ?string $subject;

    public function __construct(
        public Mailbox $mailbox,
        ?string $subject = null,
    ) {
        $subject = null !== $subject ? trim($subject) : '';
        $this->subject = '' !== $subject ? mb_substr($subject, 0, self::SUBJECT_MAX) : null;
    }
}
