<?php

namespace App\Dto;

use App\Messaging\Mailbox;
use App\Messaging\MessageBody;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/** Nouveau fil : soit vers un adhérent (recipientId), soit vers une boîte du bureau (mailbox). */
final class NewConversation
{
    public function __construct(
        #[Assert\Uuid]
        public readonly ?string $recipientId = null,

        #[Assert\Choice(callback: [self::class, 'mailboxes'])]
        public readonly ?string $mailbox = null,

        #[Assert\Length(max: 120)]
        public readonly ?string $subject = null,

        #[Assert\NotBlank]
        #[Assert\Length(max: MessageBody::MAX)]
        public readonly string $body = '',
    ) {
    }

    /** @return list<string> */
    public static function mailboxes(): array
    {
        return array_map(static fn (Mailbox $m): string => $m->value, Mailbox::cases());
    }

    #[Assert\Callback]
    public function validateTarget(ExecutionContextInterface $context): void
    {
        if ((null === $this->recipientId) === (null === $this->mailbox)) {
            $context->buildViolation('Indique soit un destinataire, soit une boîte du bureau.')
                ->atPath('recipientId')
                ->addViolation();
        }
    }
}
