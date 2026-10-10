<?php

namespace App\Dto;

use App\Messaging\MessageBody;
use Symfony\Component\Validator\Constraints as Assert;

final class MessageInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: MessageBody::MAX)]
        public readonly string $body = '',
    ) {
    }
}
