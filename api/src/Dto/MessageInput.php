<?php

declare(strict_types=1);

namespace App\Dto;

use App\Messaging\MessageBody;
use App\Text\Multiline;
use Symfony\Component\Validator\Constraints as Assert;

final class MessageInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: MessageBody::MAX)]
        #[Multiline]
        public readonly string $body = '',
    ) {
    }
}
